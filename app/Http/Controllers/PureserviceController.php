<?php

namespace App\Http\Controllers;

use Psr\Http\Message\{RequestInterface, ResponseInterface};
use GuzzleHttp\{Client, HandlerStack, Middleware, RetryMiddleware, RequestOptions};
use Carbon\Carbon;
use Illuminate\Support\{Str, Arr};

class PureserviceController extends Controller
{
    protected $api;
    protected $options;
    protected $statuses = [];
    protected $typeIds = [];
    protected $pre = '/agent/api'; // Standard prefix for API-kall
    protected $ticketOptions = [];

    /**
     * Oppgi $fetchTypeIds som true for at assetType-IDer skal lastes inn
     */
    public function __construct($fetchTypeIds = false) {
        $this->getClient();
        $this->setOptions();
        if ($fetchTypeIds) $this->fetchTypeIds();
        //$this->fetchAssetStatuses();
    }

    /**
     * Oppretter en GuzzleHttp-klient til bruk mot Pureservice
     */
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

    /**
     * Setter standardvalg for GuzzleHttp-klienten
     */
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

    /**
     * Brukes til å kjøre en GET-forespørsel mot Pureservice
     * @param   string  $uri                Relativ URI for forespørselen
     * @param   bool    $returnResponse     Angir om returverdien skal være et responsobjet eller et array
     *
     * @return  Psr\Http\Message\ResponseInterface/assoc_array  Resultat som array eller objekt
     */
    public function apiGet($uri, $returnResponse=false) {
        $uri = $this->pre.$uri;
        $response = $this->api->get($uri, $this->options);
        if ($returnResponse) return $response;
        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * Brukes til å kjøre en POST-forespørsel mot Pureservice
     * @param   string      $uri    Relativ URI for forespørselen
     * @param   assoc_array $body   JSON-innholdet til forespørselen, som assoc_array
     * @param   string      $ct     Content-Type for forespørselen, med standardverdi
     *
     * @return  Psr\Http\Message\ResponseInterface  Resultatobjekt for forespørselen
     */
    public function apiPOST($uri, $body, $ct='application/json; charset=utf-8') {
        $uri = $this->pre.$uri;
        $options = $this->options;
        $options['json'] = $body;
        if ($ct) $options['headers']['Content-Type'] = $ct;
        return $this->api->post($uri, $options);
    }

     /**
     * Brukes til å kjøre en PATCH-forespørsel mot Pureservice
     * @param   string      $uri    Relativ URI for forespørselen
     * @param   assoc_array $body   JSON-innholdet til forespørselen, som assoc_array
     *
     * @return  Psr\Http\Message\ResponseInterface  Resultatobjekt for forespørselen
     */
    public function apiPATCH($uri, $body) {
        $uri = $this->pre.$uri;
        $options = $this->options;
        $options['json'] = $body;
        return $this->api->patch($uri, $options);
    }

    /**
     * Brukes til å kjøre en DELETE-forespørsel mot Pureservice
     * @param   string      $uri    Relativ URI for forespørselen
     *
     * @return  bool                true hvis slettingen ble gjennomført, false hvis ikke
     */
    public function apiDelete($uri) {
        $uri = $this->pre.$uri;
        $response = $this->api->delete($uri, $this->options);
        if ((int) $response->getStatusCode() >= 300):
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

    /**
     * Universell funksjon for å hente ID på et objekt oppgitt med navn
     * @param string    $entity     Objektnavnet, f.eks. 'status', '
     * @param string    $name       Navnet på enheten som skal finnes
     * @param bool      $useKey     Instruerer funksjonen til å lete i 'key' i stedet for 'name'
     *
     * @return mixed      ID for enheten eller null
     */
    protected function getEntityId($entity, $name, $useKey = false) {
        $entity = Str::lower($entity);
        $uri = $useKey ? '/'.$entity.'/?filter=key == "'.$name.'"' : '/'.$entity.'/?filter=name == "'.$name.'"';
        $entities = Str::plural($entity);
        if ($result = $this->apiGet($uri)):
            if (count($result[$entities]) > 0) return $result[$entities][0]['id'];
        endif;
        return null; // Hvis ikke funnet

    }

    /**
     * Universell funksjon for å hente et objekt oppgitt med navn
     * @param string    $entity     Objektnavnet, f.eks. 'status', '
     * @param string    $name       Navnet på enheten som skal finnes
     * @param bool      $useKey     Instruerer funksjonen til å lete i 'key' i stedet for 'name'
     *
     * @return mixed    assoc_array for enheten eller null
     */
    protected function getEntityByName($entity, $name, $useKey = false) {
        $entity = Str::lower($entity);
        $uri = $useKey ? '/'.$entity.'/?filter=key == "'.$name.'"' : '/'.$entity.'/?filter=name == "'.$name.'"';
        $entities = Str::plural($entity);
        if ($result = $this->apiGet($uri)):
            if (count($result[$entities]) > 0) return $result[$entities][0];
        endif;
        return null; // Hvis ikke funnet
    }

    /**
     * Henter ned standardinnstillinger for å opprette saker i PS
     *
     * Setter variabelen $this->ticketOptions
     */
    public function setTicketOptions() {
        $this->ticketOptions = [
            'zoneId' => $this->getEntityId('department', config('svarinn.pureservice.zone')),
            'teamId' => $this->getEntityId('team', config('svarinn.pureservice.team')),
            'sourceId' => $this->getEntityId('source', config('svarinn.pureservice.source')),
            'requestTypeId' => $this->getEntityId('requesttype', config('svarinn.pureservice.requestType'), true),
            'priorityId' => $this->getEntityId('priority', config('svarinn.pureservice.priority')),
            'statusId' => $this->getEntityId('status', config('svarinn.pureservice.status')),
            'ticketTypeId' => $this->getEntityId('tickettype', config('svarinn.pureservice.ticketType')),
        ];

    }
    /**
     * Returnerer $this->ticketOptions
     */
    public function getTicketOptions() {
        return $this->ticketOptions;
    }
    /**
     * Henter relasjoner for en gitt ressurs
     * @param string    $assetId    Ressursens ID
     *
     * @return assoc_array  Array over relasjonene knyttet til ressursen
     */
    public function getRelationships($assetId) {
        $uri = '/relationship/' . $assetId . '/fromAsset?include=type,type.relationshipTypeGroup,toUser,toUser.emailaddress&filter=toUserId != NULL';
        return $this->apiGet($uri);
    }

    /**
     * Sletter en relasjon i Pureservice
     * @param   string  $relationshipId     Relasjonen sin ID
     *
     * @return  bool    angir om slettingeb lyktes eller ikke
     */
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

    /**
     * Henter alle datamaskin- og mobilenhet-ressurser fra Pureservice
     * @return  assoc_array     Array over ressursene
     */
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

    /**
     * Kobler en ressurs til brukernavn
     * @param string    $assetId    Ressursens ID
     * @param array     $usernames  Array over brukernavn som skal relateres til ressursen
     * @param string    $type       Angir ressurstypen, slik at man bruker korrekt relasjons-ID
     *
     * @return bool     Angir om koblingen ble utført eller ikke
     */
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

    public function findCompany($orgNo=null, $companyName=null) {
        $include = '&include=phonenumber,emailAddress';
        if ($orgNo != null):
            $uri = '/company/?filter=(!disabled AND organizationNumber=="'.$orgNo.'")'.$include;
            $companiesByOrgNo = $this->apiGet($uri)['companies'];
        else:
            $companiesByOrgNo = [];
        endif;
        if ($companyName != null):
            $uri = '/company/?filter=(!disabled AND name=="'.$companyName.'")'.$include;
            $companiesByName = $this->apiGet($uri)['companies'];
        else:
            $companiesByName = [];
        endif;
        $companyByOrgNo = count($companiesByOrgNo) > 0 ? $companiesByOrgNo[0]: false;
        $companyByName = count($companiesByName) > 0 ? $companiesByName[0]: false;
        // Gitt at begge selskaper blir funnet, er de det samme?
        $sameCompany = ($companyByName && $companyByOrgNo) && ($companyByName['id'] == $companyByOrgNo['id']);
        if ($sameCompany || (!$companyByName && $companyByOrgNo)):
            return $companyByOrgNo;
       else:
            return $companyByName;
        endif;
    }

    /**
     * Oppretter et firma i Pureservice, og legger til e-post og telefonnr hvis oppgitt
     * @param string $companyName   Foretaksnavn, påkrevd
     * @param string $orgNo         Foretakets organisasjonsnr. Viktig for at SvarInn-integrasjon skal virke
     * @param string $email         Foretakets e-postadresse
     * @param string $phone         Foretakets telefonnr
     *
     * @return mixed    array med det opprettede foretaket eller false hvis oppretting feilet
     */
    public function addCompany($companyName, $orgNo=null, $email=false, $phone=false) {
        $phoneId = null;
        $emailId = $email ? $this->findEmailaddressId($email, true): null;
        if ($email != null):
            $uri = '/companyemailaddress/';
            $body = [
                'email' => $email,
            ];
            if ($response = $this->apiPOST($uri, $body)):
                $result = json_decode($response->getBody()->getContents(), true);
                $emailId = $result['companyemailaddresses'][0]['id'];
            endif;
        endif;
        if ($phone != null):
            $uri = '/phonenumber/';
            $body = [];
            $body['phonenumbers'][] = [
                'number' => $phone,
                'type' => 2,
            ];
            if ($response = $this->apiPOST($uri, $body)):
                $result = json_decode($response->getBody()->getContents(), true);
                $phoneId = $result['phonenumbers'][0]['id'];
            endif;
        endif;

        // Oppretter selve foretaket
        $uri = '/company?include=phonenumber,emailAddress';
        $body = [
            'name' => $companyName,
            'organizationNumber' => $orgNo
        ];
        if ($emailId != null) $body['emailAddressId'] = $emailId;

        if ($phoneId != null) $body['phonenumberId'] = $phoneId;

        if ($response = $this->apiPOST($uri, $body)):
            $result = json_decode($response->getBody()->getContents(), true);
            return $result['companies'][0];
        endif;

        return false;
    }

    public function findUser($email) {
        $uri = '/user/?limit=1&include=emailAddress&filter=emailaddress.email == "'.$email.'"';
        if ($result = $this->apiGet($uri)):
            if (count($result['users']) == 1) return $result['users'][0];
        endif;
        return false;
    }

    /**
     * Henter ID for e-postadresse registrert i Pureservice
     * @param string    $email          E-postadressen
     * @param bool      $companyAddress Angir om man skal se etter en firma-adresse
     *
     * @return mixed    null hvis den ikke finnes, IDen dersom den finnes.
     */
    protected function findEmailaddressId($email, $companyAddress=false) {
        $prefix = $companyAddress ? 'company' : '';
        $uri = '/'.$prefix.'emailaddress?filter=email == "'.$email.'"';
        if ($result = $this->apiGet($uri)):
            $found = count($result[$prefix.'emailaddresses']);
            if ($found > 0):
                return $result[$prefix.'emailaddresses'][0]['id'];
            endif;
        endif;
        return null; // Hvis ikke funnet
    }

    /**
     * Oppretter en standardbruker for foretak/virksomhet
     */
    public function addCompanyUser($companyInfo, $emailaddress = false) {
        $emailId = $this->findEmailaddressId($emailaddress);
        if ($emailaddress && $emailId == null):
            $uri = '/emailaddress/';
            $body = [
                'email' => $emailaddress,
            ];
            if ($response = $this->apiPOST($uri, $body)):
                $result = json_decode($response->getBody()->getContents(), true);
                $emailId = $result['emailaddresses'][0]['id'];
            endif;
        endif;

        $uri = '/user/?include=emailAddress,company';
        $body = [
            'firstName' => 'SvarUt',
            'lastName' => Str::limit($companyInfo['name'], 100),
            'role' => config('svarinn.pureservice.role_id'),
            'emailAddressId' => $emailId,
            'companyId' => $companyInfo['id'],
            'notificationScheme' => 0,
        ];

        if ($response = $this->apiPOST($uri, $body)):
            $result = json_decode($response->getBody()->getContents(), true);
            return $result['users'][0];
        endif;
        return false;
    }

    /**
     * Oppretter en sak i Pureservice basert på en SvarUt-melding
     * @param Collection    $message        Metadata fra SvarUt
     * @param array         $attachments    Stier til filer som skal legges til som vedlegg
     *
     * @return mixed    False dersom oppretting mislykkes, RequestNumber dersom det går bra
     */
    public function createTicketFromSvarUt($message, $user) {
        if ($this->ticketOptions == []) $this->setTicketOptions();
        $uri = '/ticket';
        $description = '<p>SvarUt Forsendelses-ID: <strong>'.$message['id'].'</strong></p>'.PHP_EOL;
        if ($message['date'] > 0):
            $description .= '<p>Dato: '.$this->dateFromEpochTime($message['date']).'</p>'.PHP_EOL;
        endif;
        if ($message['svarPaForsendelse'] != null):
            $description .= '<p>Svar på forsendelse: '.$message['svarPaForsendelse'].'</p>'.PHP_EOL;
        endif;
        $description .= '<p><strong>Data fra avleverende system</strong></p>'.PHP_EOL;
        $description .= '<ul>'.PHP_EOL;
        foreach (Arr::get($message, 'metadataFraAvleverendeSystem') as $field => $value):
            if ($field == 'ekstraMetadata') continue;
            if (in_array($field, ['journaldato', 'dokumentetsDato']) && $value > 0):
                $value = $this->dateFromEpochTime($value);
            endif;
            $description .= '  <li>'.$field.': '.$value.'</li>'.PHP_EOL;
        endforeach;
        $description .= '</ul>'.PHP_EOL;
        $description .= '<p> </p>'.PHP_EOL;
        $description .= '<p>Se vedlegg for selve forsendelsen</p>'.PHP_EOL;
        $body = [
            'subject' => $message['tittel'],
            'description' => $description,
            'userId' => $user['id'],
            'visibility' => config('svarinn.pureservice.visibility'),
            'assignedDepartmentId' => $this->ticketOptions['zoneId'],
            'assignedTeamId' => $this->ticketOptions['teamId'],
            'sourceId' => $this->ticketOptions['sourceId'],
            'ticketTypeId' => $this->ticketOptions['ticketTypeId'],
            'priorityId' => $this->ticketOptions['priorityId'],
            'statusId' => $this->ticketOptions['statusId'],
            'requestTypeId' => $this->ticketOptions['requestTypeId'],
        ];

        if ($response = $this->apiPOST($uri, $body)):
            return json_decode($response->getBody()->getContents(), true)['tickets'][0];
        endif;

        return false;
    }

    /**
     * Laster opp vedlegg til en sak i Pureservice
     * @param array         $attachments    Array over filstier som skal lastes opp
     * @param assoc_array   $ticket         Saken som assoc_array
     * @param assoc_array   $message        SvarUt-meldingen som assoc_array
     *
     * @return assoc_array  Rapport på status og antall filer/opplastinger
     */
    public function uploadAttachments($attachments, $ticket, $message) {
        $uri = '/attachment';
        $msgFiles = collect(Arr::get($message, 'filmetadata'));
        $attachmentCount = count($attachments);
        $uploadCount = 0;
        $status = 'OK';
        foreach ($attachments as $file):
            $filename = basename($file);
            $filmetadata = $msgFiles->firstWhere('filnavn', $filename);
            $body = [
                'name' => Str::beforeLast($filename, '.'),
                'fileName' => $filename,
                'size' => $this->human_filesize(filesize($file)),
                'contentType' => $filmetadata['mimetype'],
                'ticketId' => $ticket['id'],
                'bytes' => base64_encode(file_get_contents($file)),
            ];
            if ($result = $this->apiPOST($uri, $body, 'application/vnd.api+json')):
                $uploadCount++;
            else:
                $status = 'Error';
            endif;
        endforeach;
        return [
            'status' => $status,
            'fileCount' => $attachmentCount,
            'uploadCount' => $uploadCount,
        ];
    }

    protected function human_filesize($bytes, $decimals = 2) {
        $sz = 'BKMGTP';
        $factor = floor((strlen($bytes) - 1) / 3);
        return sprintf("%.{$decimals}f", $bytes / pow(1024, $factor)) . @$sz[$factor];
    }

    protected function dateFromEpochTime($ts) {
        return Carbon::createFromTimestampMs($ts, config('app.timezone'))
            ->locale(config('app.locale'))
            ->toDateTimeString();
    }

}
