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
    protected $typeIds = [];
    protected $pre = '/agent/api'; // Standard prefix for API-kall
    public $up = false;

    public function __construct() {
        $this->getClient();
        $this->setOptions();
        $this->fetchTypeIds();
        //$this->fetchAssetStatuses();
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

    public function apiGet($uri, $returnResponse=false) {
        $uri = $this->pre.$uri;
        $response = $this->api->get($uri, $this->options);
        if ($returnResponse) return $response;
        return json_decode($response->getBody()->getContents(), true);
    }

    public function apiPOST($uri, $body, $contentType='application/json; charset=utf-8') {
        $uri = $this->pre.$uri;
        $options = $this->options;
        $options['json'] = $body;
        $options['headers']['Content-Type'] = $contentType;
        return $this->api->post($uri, $options);
    }

    public function apiPATCH($uri, $body) {
        $uri = $this->pre.$uri;
        $options = $this->options;
        $options['json'] = $body;
        return $this->api->patch($uri, $options);
    }

    public function apiDelete($uri) {
        $uri = $this->pre.$uri;
        $response = $this->api->delete($uri, $this->options);
        if ($response->getStatusCode() != 200):
            return false;
        endif;
        return true;
    }

    /** fetchTypeIds
     * Henter inn IDer til forskjellige innholdstyper.
     * Befolker følgende config-verdier med verdier fra Pureservice:
     *  config('pureservice.computer.asset_type_id')
     *  config('pureservice.computer.className')
     *  config('pureservice.computer.status')
     *  config('pureservice.computer.relationship_type_id')
     *  config('pureservice.computer.properties')
     *
     *  config('pureservice.mobile.asset_type_id')
     *  config('pureservice.mobile.className')
     *  config('pureservice.mobile.status')
     *  config('pureservice.mobile.relationship_type_id')
     *  config('pureservice.mobile.properties')
     * @return void
     */
    protected function fetchTypeIds() {
        // Henter ut relasjonstyper allerede i bruk i basen
        $uri = '/relationship/?include=type&filter=toAssetId!=null AND fromUserId!=null AND solvingRelationship == false';
        $result = $this->apiGet($uri);
        $relationshipTypes = collect($result['linked']['relationshiptypes']);
        $this->statuses = [];
        foreach(['computer', 'mobile'] as $type):
            // Henter ut ressurstypen basert på displayName
            $uri = '/assettype/?filter=name.equals("'.config('pureservice.'.$type.'.displayName').'")&include=fields,statuses';
            $result = $this->apiGet($uri);
            if (count($result['assettypes']) > 0):
                // setter asset_type_id og className i config basert på resultatet
                config(['pureservice.'.$type.'.asset_type_id' => $result['assettypes'][0]['id']]);
                config(['pureservice.'.$type.'.className' => '_'.config('pureservice.'.$type.'.asset_type_id').'_Assets_'.config('pureservice.'.$type.'.displayName')]);

                // Henter ut status-IDer
                $raw_statuses = collect($result['linked']['assetstatuses']);
                $this->statuses[$type] = [];
                foreach (config('pureservice.'.$type.'.status') as $key=>$value):
                    $raw_status = $raw_statuses->firstWhere('name', $value);
                    $statusId = $raw_status != null ? $raw_status['id'] : null;
                    $this->statuses[$type][$key] = $statusId;
                endforeach;
            endif;

            // Finner propertyName for feltnavnene definert i config('pureservice.'.$type.'.fields')
            $properties = collect($result['linked']['assettypefields']);
            foreach (config('pureservice.'.$type.'.fields') as $key => $fieldName):
                $property = $properties->firstWhere('name', $fieldName);
                config(['pureservice.'.$type.'.properties.'.$key => lcfirst($property['propertyName'])]);
            endforeach;

            // Finner relasjonstypens ID for brukerkoblingen
            if ($relationshipType = $relationshipTypes->firstWhere('fromAssetTypeId', config('pureservice.'.$type.'.asset_type_id'))):
                config(['pureservice.'.$type.'.relationship_type_id' => $relationshipType['id']]);
            endif;
        endforeach;
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
        $fn = config('pureservice.'.$type.'.properties');
        // Standard status for nye enheter
        $status = $this->statuses[$type]['active_inStorage'];

        $today = Carbon::today();
        $EOL = Carbon::create($psAsset[$fn['EOL']]);
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
        $type = &$psAsset['type'];
        $fn = config('pureservice.'.$type.'.properties');
        $status = $psAsset['statusId'];
        $active_statuses = [
            $this->statuses[$type]['active_deployed'],
            $this->statuses[$type]['active_inStorage'],
            $this->statuses[$type]['active_phaseOut']
        ];
        if (in_array($status, $active_statuses)):
            $today = Carbon::today();
            $EOL = Carbon::create($psAsset[$fn['EOL']]);
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


    public function createAsset($psAsset) {
        $type = $psAsset['type'];
        $uri = '/asset/'.config('pureservice.'.$type.'.className');
        $statusId = (string) $this->getInitialStatus($psAsset);
        $psAsset['links'] = [
            'type' => ['id' => config('pureservice.'.$type.'.asset_type_id')],
            'status' => ['id' => $statusId]
        ];
        $psAsset = collect($psAsset)->except(['usernames', 'type']);
        $body = [
            config('pureservice.'.$type.'.className') => [
                $psAsset->toArray()
            ]
        ];
        $response = $this->apiPOST($uri, $body);
        unset($uri, $body, $psAsset);
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

    /** updateAssetDetail
     * Kjører patch på detaljer på gitt Puserservice Asset
     *
     */
    public function updateAssetDetail($psId, $body) {
        $uri = '/asset/'.$psId;

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
