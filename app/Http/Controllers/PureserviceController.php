<?php

namespace App\Http\Controllers;

use Psr\Http\Message\{RequestInterface, ResponseInterface};
use GuzzleHttp\{Client, HandlerStack, Middleware, RetryMiddleware};
use Carbon\Carbon;

class PureserviceController extends Controller
{
    protected $api;
    protected $options;
    public $statuses;
    protected $pre = '/agent/api';

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

    protected function apiGet($uri) {
        $response = $this->api->request('GET', $uri, $this->options);
        return json_decode($response->getBody()->getContents(), true);
    }

    protected function apiPOST($uri, $body) {
        $response = $this->api->request('POST', $uri, $this->options);
        return json_decode($response->getBody()->getContents(), true);
    }

    protected function apiPUT($uri, $body) {
        $response = $this->api->request('PUT', $uri, $this->options);
        return json_decode($response->getBody()->getContents(), true);
    }

    protected function fetchAssetStatuses($class = null) {
        if ($class == null) $class = config('pureservice.className');
        $uri = $this->pre.'/assettype/?include=fields,statuses&filter=uniqueClassName.equals("'.$class.'")';
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
        $uri = $this->pre.'/asset/?filter=uniqueId.equals("'.$serial.'")&uniqueClassName.equals("'.config('pureservice.className').'")';
        $result = $this->apiGet($uri);

        // Hvis eiendelen ikke finnes i basen
        if ($result['assets'] == []) return false;

        return $result['assets'][0];

    }

    public function getAllAssets() {
        $uri = $this->pre.'/asset/?filter=typeID=='.config('pureservice.asset_type_id');
        return $this->apiGet($uri);
    }

    public function createAsset($psAsset) {
        $fp = config('pureservice.field_prefix');
        $uri = $this->pre.'/asset/'.config('pureservice.className');
        $body = [];

    }
}
