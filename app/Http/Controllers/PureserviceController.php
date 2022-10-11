<?php

namespace App\Http\Controllers;

use Psr\Http\Message\{RequestInterface, ResponseInterface};
use GuzzleHttp\{Client, HandlerStack, Middleware, RetryMiddleware, RequestOptions};
use Carbon\Carbon;

class PureserviceController extends Controller
{
    protected $api;
    protected $options;
    public $statuses;
    protected $pre = '/agent/api'; // Standard prefix for API-kall

    public function __construct() {
        $this->getClient();
        $this->setOptions();
        $this->fetchAssetStatuses();
    }

    private function getClient() {
        $maxRetries = config('pureservice.maxretries', 3);

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
            'base_uri' => config('pureservice.api_url'),
            'timeout'         => 30,
            'allow_redirects' => false,
            'handler' => $stack
        ]);
    }
    private function setOptions() {
        $this->options = [
            'headers' => [
                'X-Authorization-Key' => config('pureservice.apikey'),
                'Accept' => 'application/vnd.api+json',
                'Connection' => 'keep-alive',
                'Accept-Encoding' => 'gzip, deflate',
            ]
        ];
        return false;
    }

    protected function apiGet($uri, $returnResponse=false) {
        $uri = $this->pre.$uri;
        $response = $this->api->request('GET', $uri, $this->options);
        if ($returnResponse) return $response;
        return json_decode($response->getBody()->getContents(), true);
    }

    protected function apiPOST($uri, $body) {
        $uri = $this->pre.$uri;
        $options = $this->options;
        $options[RequestOptions::JSON] = $body;
        $response = $this->api->request('POST', $uri, $options);
        return json_decode($response->getBody()->getContents(), true);
    }

    protected function apiPUT($uri, $body) {
        $uri = $this->pre.$uri;
        $options = $this->options;
        $options[RequestOptions::JSON] = $body;
        $response = $this->api->request('PUT', $uri, $options);
        return json_decode($response->getBody()->getContents(), true);
    }

    protected function fetchAssetStatuses($class = null) {
        if ($class == null) $class = config('pureservice.className');
        $uri = '/assettype/'. config('pureservice.asset_type_id') .'?include=fields,statuses';
        $result = $this->apiGet($uri);
        $raw_statuses = collect($result['linked']['assetstatuses']);
        $config_statuses = config('pureservice.status');
        $this->statuses = [];
        foreach ($config_statuses as $key=>$value):
            $raw_status = $raw_statuses->firstWhere('name', $value);
            $statusId = $raw_status != null ? $raw_status['id'] : null;
            $this->statuses[$key] = $statusId;
        endforeach;
    }

    public function getAssetIfExists($serial) {
        $uri = '/asset/?filter=uniqueId.equals("'.$serial.'")&uniqueClassName.equals("'.config('pureservice.className').'")';
        $result = $this->apiGet($uri);

        // Hvis eiendelen ikke finnes i basen
        if ($result['assets'] == []) return false;

        return $result['assets'][0];

    }

    public function getRelationships($assetId) {
        $uri = '/relationship/' . $assetId . '/fromAsset?include=type,type.relationshipTypeGroup,toUser,toUser.emailaddress&filter=toUserId != NULL';
        return $this->apiGet($uri);
    }

    public function getRelatedUsernames($assetId) {
        if ($relations = $this->getRelationships($assetId)['linked']):
            $linkedUsers = &$relations['users'];
            $linkedEmails = collect($relations['emailaddresses']);
            $usernames = [];
            foreach($linkedUsers as $user):
                $usernames[] = $linkedEmails->firstWhere('id', $user['emailAddressId'])['email'];
            endforeach;
            return $usernames;
        endif;
        return null;
    }

    public function getAllAssets() {
        $uri = '/asset/?filter=typeID=='.config('pureservice.asset_type_id');
        $assets = $this->apiGet($uri)['assets'];
        foreach ($assets as &$asset):
            $asset['usernames'] = $this->getRelatedUsernames($asset['id']);
        endforeach;
        return $assets;
    }

    /** getStatus
     * Bestemmer initiell status før oppretting av asset i Pureservice
     * @param   assoc_array $psAsset
     * @return  integer
     */
    protected function getInitialStatus($psAsset) {
        $fp = config('pureservice.field_prefix');
        // Standard status for nye enheter
        $status = $this->statuses['active_inStorage'];

        $today = Carbon::today();
        $EOL = Carbon::create($psAsset[$fp.'EOL']);
        if (count($psAsset['usernames']) > 0):
            // Enheten er tildelt en bruker
            $status = $this->statuses['active_deployed'];
        endif;
        // Dersom EOL er mindre enn 3 mnd unna settes status til utfasing
        if ($EOL->lessThanOrEqualTo($today->copy()->addMonth(3))) $status = $this->statuses['active_phaseOut'];

        return $status;
    }

    public function createAsset($psAsset) {
        $fp = config('pureservice.field_prefix');
        $uri = '/asset/'.config('pureservice.className');
        $statusId = (string) $this->getInitialStatus($psAsset);
        $usernames = $psAsset['usernames'];
        $psAsset = collect($psAsset)->except('usernames');
        $psAsset['links'] = [
            'type' => ['id' => config('pureservice.asset_type_id')],
            'status' => ['id' => $statusId]
        ];
        $body = [config('pureservice.className') => []];
        $body[config('pureservice.className')][] = $psAsset->toArray();

        $response = $this->apiPOST($uri, $body);
        dd($response);
    }
}
