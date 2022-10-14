<?php

namespace App\Http\Controllers;

use Psr\Http\Message\{RequestInterface, ResponseInterface};
use GuzzleHttp\{Client, HandlerStack, Middleware, RetryMiddleware, RequestOptions};
use Carbon\Carbon;

class PureserviceController extends Controller
{
    protected $api;
    protected $options;
    protected $statuses = [];
    protected $pre = '/agent/api'; // Standard prefix for API-kall
    public $up = false;

    public function __construct() {
        $this->getClient();
        $this->setOptions();
        $this->fetchAssetStatuses();
        if (count($this->statuses)) $this->up = true;
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
                'Content-Type' => 'application/json; charset=utf-8',
                'Connection' => 'keep-alive',
                'Accept-Encoding' => 'gzip, deflate',
            ]
        ];
        //$this->options['http_errors'] = false;
        return false;
    }

    protected function apiGet($uri, $returnResponse=false) {
        $uri = $this->pre.$uri;
        $response = $this->api->get($uri, $this->options);
        if ($returnResponse) return $response;
        return json_decode($response->getBody()->getContents(), true);
    }

    protected function apiPOST($uri, $body, $contentType='application/json; charset=utf-8') {
        $uri = $this->pre.$uri;
        $options = $this->options;
        $options['json'] = $body;
        $options['headers']['Content-Type'] = $contentType;
        return $this->api->post($uri, $options);
    }


    protected function apiPATCH($uri, $body) {
        $uri = $this->pre.$uri;
        $options = $this->options;
        $options['json'] = $body;
        return $this->api->patch($uri, $options);
    }
    protected function apiDELETE($uri,) {
        $uri = $this->pre.$uri;
        $response = $this->api->delete($uri, $this->options);
        if ($response->getStatusCode() != 200):
            return false;
        endif;
        return true;
    }

    protected function fetchAssetStatuses() {
        $this->statuses = [];
        foreach(['computer', 'mobile'] as $type):
            $uri = '/assettype/'. config('pureservice.'.$type.'.asset_type_id') .'?include=fields,statuses';
            $result = $this->apiGet($uri);
            $raw_statuses = collect($result['linked']['assetstatuses']);
            $this->statuses[$type] = [];
            foreach (config('pureservice.'.$type.'.status') as $key=>$value):
                $raw_status = $raw_statuses->firstWhere('name', $value);
                $statusId = $raw_status != null ? $raw_status['id'] : null;
                $this->statuses[$type][$key] = $statusId;
            endforeach;
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

    public function deleteRelation($relationshipId) {
        return $this->apiDELETE('/relationship/'.$relationshipId.'/delete');
    }

    public function getRelatedUsernames($assetId) {
        $relations_full = $this->getRelationships($assetId);

        if (count($relations_full['relationships']) == 0) return [];

        $linkedUsers = &$relations_full['linked']['users'];
        $linkedEmails = collect($relations_full['linked']['emailaddresses']);
        $usernames = [];
        foreach($linkedUsers as $user):
            $usernames[] = $linkedEmails->firstWhere('id', $user['emailAddressId'])['email'];
        endforeach;
        return $usernames;
    }

    public function getAllAssets() {
        $totalAssets = [];
        foreach (['computer', 'mobile'] as $type):
            $uri = '/asset/?filter=typeID=='.config('pureservice.'.$type.'.asset_type_id');
            $assets = $this->apiGet($uri)['assets'];
            foreach ($assets as $asset):
                $asset['type'] = $type;
                $asset['usernames'] = $this->getRelatedUsernames($asset['id']);
                $totalAssets[] = $asset;
            endforeach;
        endforeach;
        return $totalAssets;
    }

    /** getInitialStatus
     * Bestemmer initiell status før oppretting av asset i Pureservice
     * @param   psAsset     assoc_array     Asset-array som følger Pureservice sine felt-definisjoner
     * @return  integer                     Status-ID som kan brukes mot Pureservice
     */
    protected function getInitialStatus($psAsset) {
        $type = &$psAsset['type'];
        $fp = config('pureservice.field_prefix');
        // Standard status for nye enheter
        $status = $this->statuses[$type]['active_inStorage'];

        $today = Carbon::today();
        $EOL = Carbon::create($psAsset[$fp.'EOL']);
        if (count($psAsset['usernames']) > 0):
            // Enheten er tildelt en bruker
            $status = $this->statuses[$type]['active_deployed'];
        endif;
        // Dersom EOL er mindre enn 3 mnd unna settes status til utfasing
        if ($EOL->lessThanOrEqualTo($today->copy()->addMonth(3))) $status = $this->statuses[$type]['active_phaseOut'];

        return $status;
    }

    /** calculateStatus
     * Bestemmer statusendring for eksisterende asset.
     * @param   psAsset         assoc_array   Asset-array som følger Pureservice sine felt-definisjoner
     * @param   notDeployed     boolean       Bestemmer om vurderingen skal ta høyde for at maskinen ikke skal være tildelt
     *
     * @return  integer                       Status-ID som kan brukes mot Pureservice
     */
    public function calculateStatus($psAsset, $notDeployed=false) {
        $fp = config('pureservice.field_prefix');
        $type = &$psAsset['type'];
        $status = $psAsset['statusId'];
        $active_statuses = [
            $this->statuses[$type]['active_deployed'],
            $this->statuses[$type]['active_inStorage'],
            $this->statuses[$type]['active_phaseOut']
        ];
        if (in_array($status, $active_statuses)):
            $today = Carbon::today();
            $EOL = Carbon::create($psAsset[$fp.'EOL']);
            if ($EOL->lessThanOrEqualTo($today->copy()->addMonth(3))) $status = $this->statuses[$type]['active_phaseOut'];
            if ($notDeployed && ($status == $this->statuses[$type]['active_deployed'])) $status = $this->statuses[$type]['active_phaseOut'];
        endif;
        return $status;
    }


    public function relateAssetToUsernames($assetId, $usernames, $type='computer') {
        $jsonBody = ['relationships' => []];
        if (! is_array($usernames)) $usernames = [$usernames];
        foreach ($usernames as $username):
            // Finner userId for brukeren gjennom e-postadressen
            $uri = '/emailaddress/?filter=email == "'.$username.'"';
            $emailaddresses = $this->apiGet($uri)['emailaddresses'];

            if (count($emailaddresses) > 0):
                $userId = $emailaddresses[0]['userId'];

                $user_relationship = [
                    'main' => 'ToAssetId',
                    'inverseMain' => 'FromAssetId',

                    'links' => [
                        'type' => ['id' => (int) config('pureservice.'.$type.'.relationship_type_id')],
                        'toAsset' => ['id' => $assetId],
                        'fromUser' => ['id' => $userId],
                    ]
                ];
                $jsonBody['relationships'][] = $user_relationship;
            endif;
        endforeach;

        if (count($jsonBody['relationships']) > 0):
            $uri = '/relationship/';
            if ($relationship = $this->apiPOST($uri, $jsonBody, 'application/vnd.api+json')):
                return true;
            endif;
        endif;
        return false;
    }


    public function createAsset($jamfAsset) {
        $type = $jamfAsset['type'];
        $uri = '/asset/'.config('pureservice.'.$type.'.className');
        $statusId = (string) $this->getInitialStatus($jamfAsset);
        $jamfAsset = collect($jamfAsset)->except(['usernames', 'type']);
        $jamfAsset['links'] = [
            'type' => ['id' => config('pureservice.'.$type.'.asset_type_id')],
            'status' => ['id' => $statusId]
        ];
        $body = [
            config('pureservice.'.$type.'.className') => [
                $jamfAsset->toArray()
            ]
        ];
        $response = $this->apiPOST($uri, $body);
        unset($uri, $body, $jamfAsset);
        if ($response->getStatusCode() == 200):
            $psAsset = json_decode($response->getBody()->getContents(), true)['assets'][0];
            return $psAsset['id'];
        else:
            return false;
        endif;
    }

    public function updateAsset($jamfAsset, $psAsset) {
        $jamfAsset['statusId'] = $psAsset['statusId'];
        $statusId = $this->calculateStatus($jamfAsset);
        $uri = '/asset/'.$psAsset['id'];
        $jamfAsset = collect($jamfAsset)->except(['usernames', 'type']);
        /*$jamfAsset['links'] = [
            'type' => ['id' => config('pureservice.asset_type_id')],
            'status' => ['id' => $statusId]
        ];*/
        $jamfAsset['statusId'] = $statusId;
        $body = $jamfAsset->toArray();
        $response = $this->apiPATCH($uri, $body);
        unset($uri, $body, $jamfAsset, $psAsset);
        if ($response->getStatusCode() == 200):
            $psAsset = json_decode($response->getBody()->getContents(), true)['assets'][0];
            return $psAsset['id'];
        else:
            return false;
        endif;
    }

    public function updateAssetStatus($psId, $statusId) {
        $uri = '/asset/'.$psId;
        $body = [
            'statusId' => $statusId
        ];
        $response = $this->apiPATCH($uri, $body);
        unset($uri, $body);
        if ($response->getStatusCode() == 200):
            $psAsset = json_decode($response->getBody()->getContents(), true)['assets'][0];
            return $psAsset['id'];
        else:
            return false;
        endif;
    }

}
