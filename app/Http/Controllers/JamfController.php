<?php

namespace App\Http\Controllers;

use Psr\Http\Message\{RequestInterface, ResponseInterface};
use GuzzleHttp\{Client, HandlerStack, Middleware, RetryMiddleware};
use Carbon\Carbon;

use function Psy\debug;

class JamfController extends Controller
{
    //
    protected $token;
    protected $api;
    protected $options;

    public function __construct() {
        $this->getClient();
        $this->getBearerToken();
        $this->setOptions();
    }

    private function getClient() {
        $maxRetries = config('jamfpro.maxretries', 3);

        // Funksjon som finner ut om vi skal kjøre en retry
        $decider = function(int $retries, RequestInterface $request, ResponseInterface $response = null) use ($maxRetries) : bool {
            return
                $retries < $maxRetries
                && null !== $response
                && 429 === $response->getStatusCode();
        };

        // Funksjon for å finne ut hvor lenge man skal vente
        $delay = function(int $retries, ResponseInterface $response) : int {
            if (!$response->hasHeader('Retry-After')) {
                return RetryMiddleware::exponentialDelay($this->retries);
            }

            $retryAfter = $response->getHeaderLine('Retry-After');

            if (!is_numeric($retryAfter)) {
                $retryAfter = (new \DateTime($retryAfter))->getTimestamp() - time();
            }

            return (int) $retryAfter * 1000;
        };

        $stack = HandlerStack::create();
        $stack->push(Middleware::retry($decider, $delay));

        $this->api = new Client([
            'base_uri' => config('jamfpro.api_url'),
            'timeout'         => 30,
            'allow_redirects' => false,
            'handler' => $stack
        ]);
    }

    private function getBearerToken() {
        $headers = [
            'Accept' => 'application/json',
        ];
        $response = $this->api->request('POST', '/api/v1/auth/token',
            ['auth' => [config('jamfpro.username'), config('jamfpro.password')], 'headers' => $headers]);
        if ($response->getStatusCode() == 200):
            $this->token = json_decode($response->getBody()->getContents())->token;
            return false;
       else:
            die('Fikk ikke hentet API Token');
        endif;
    }

    private function setOptions() {
        $this->options = [
            'headers' => [
                'Authorization' => 'Bearer '.$this->token,
                'Accept' => 'application/json',
                'Connection' => 'keep-alive',
                'Accept-Encoding' => 'gzip, deflate',
            ]
        ];
        return false;
    }
    public function getJamfComputers() {
        $page=0;
        $page_size=100;
        $gotAll = false;
        $results = [];
        while (!$gotAll):
            $uri = '/api/v1/computers-inventory?section=GENERAL&section=HARDWARE&section=USER_AND_LOCATION&section=OPERATING_SYSTEM&page='.$page.'&page-size='.$page_size;
            $response = $this->api->request('GET', $uri, $this->options);
            $data = json_decode($response->getBody()->getContents(), true);
            $results = array_merge($results, $data['results']);
            $gotAll = $data['totalCount'] <= $page_size * ($page + 1);
            $page++;
        endwhile;
        $iResultCount = count($results);

        return $results;
    }

    public function getJamfDevices() {
        $page=0;
        $page_size=100;
        $gotAll = false;
        $results = [];
        while (!$gotAll):
            $uri = '/api/v2/mobile-devices?page='.$page.'&page-size='.$page_size;
            $response = $this->api->request('GET', $uri, $this->options);
            $data = json_decode($response->getBody()->getContents(), true);
            $results = array_merge($results, $data['results']);
            $gotAll = $data['totalCount'] <= $page_size * ($page + 1);
            $page++;
        endwhile;
        $iResultCount = count($results);
        $detailedResults = [];
        foreach ($results as $dev):
            $uri = '/api/v2/mobile-devices/'.$dev['id'].'/detail';
            $response = $this->api->request('GET', $uri, $this->options);
            $data = json_decode($response->getBody()->getContents(), true);
            $detailedResults[] = $data;
        endforeach;

        return $detailedResults;
    }

    public function getJamfAssetsAsPsAssets() {
        $fp = config('pureservice.field_prefix');
        $psClassName = config('pureservice.asset_class');
        $computers = $this->getJamfComputers();
        $psAssets = [];
        foreach ($computers as $mac):
            $psAsset = [];
            $psAsset[$fp.'Navn'] = $mac['general']['name'];
            $psAsset[$fp.'Serienr'] = $mac['hardware']['serialNumber'];
            $psAsset[$fp.'Modell'] = $mac['hardware']['model'];
            $psAsset[$fp.'ModelID'] = $mac['hardware']['modelIdentifier'];
            $psAsset[$fp.'Prosessor'] = $mac['hardware']['processorType'];
            $psAsset[$fp.'OS_45_versjon'] = $mac['operatingSystem']['version'];
            $psAsset[$fp.'Innkjøpsdato'] = Carbon::create($mac['general']['initialEntryDate'], 'Europe/Oslo')
                ->format('Y-m-d');
            $psAsset[$fp.'EOL'] = Carbon::create($mac['general']['initialEntryDate'], 'Europe/Oslo')
                ->addYears(config('pureservice.computer_lifespan', 4))->format('Y-m-d');
            $psAsset[$fp.'Sist_32_sett'] = ($mac['general']['lastContactTime'] != null) ? Carbon::create($mac['general']['lastContactTime'], 'Europe/Oslo')->format('Y-m-d') : null;
            $psAsset[$fp.'Jamf_45_URL'] = config('jamfpro.api_url').'/computers.html?id='.$mac['id'].'&o=r';

            $psAsset['username'] = $mac['userAndLocation']['username'];
            $psAssets[] = $psAsset;
            unset($psAsset);
        endforeach;
        unset($computers);

        $devices = $this->getJamfDevices();
        foreach ($devices as $dev):
            $psAsset = [];
            $psAsset[$fp.'Navn'] = $dev['name'];
            $psAsset[$fp.'Serienr'] = $dev['serialNumber'];
            $psAsset[$fp.'Modell'] = $dev[$dev['type']]['model'];
            $psAsset[$fp.'ModelID'] = $dev[$dev['type']]['modelIdentifier'];
            $psAsset[$fp.'Prosessor'] = null;
            $psAsset[$fp.'OS_45_versjon'] = $dev['osVersion'];
            $psAsset[$fp.'Innkjøpsdato'] = Carbon::create($dev['initialEntryTimestamp'], 'Europe/Oslo')
                ->format('Y-m-d');
            $psAsset[$fp.'EOL'] = Carbon::create($dev['initialEntryTimestamp'], 'Europe/Oslo')
                ->addYears(config('pureservice.device_lifespan', 3))->format('Y-m-d');
            $psAsset[$fp.'Sist_32_sett'] = ($dev['lastInventoryUpdateTimestamp'] != null) ? Carbon::create($dev['lastInventoryUpdateTimestamp'], 'Europe/Oslo')->format('Y-m-d') : null;
            $psAsset[$fp.'Jamf_45_URL'] = config('jamfpro.api_url').'/mobileDevices.html?id='.$dev['id'].'&o=r';

            $psAsset['usernames'] = [$dev['location']['username']];
            $psAssets[] = $psAsset;
            unset($psAsset);
        endforeach;
        unset($devices);
        return $psAssets;
    }
}
