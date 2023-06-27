<?php

namespace App\Services;
use App\Models\{Ticket, TicketCommunication, User, Company};
use Carbon\Carbon;
use Illuminate\Support\{Str, Arr};
use Illuminate\Support\Facades\Storage;

/**
 * Versjon 2 av Pureservice API-klient, basert på Laravel sitt HTTP Client-bibliotek
 */
class PsApi extends API {
    protected array $ticketOptions;
    protected bool $up = false;
    protected array $statuses;

    public function __construct() {
        $this->cKey = 'pureservice';
        $this->setProperties();
    }

    /**
     * Oppretter sak i Pureservice fra sak i DB
     */
    public function createTicketFromDB(Ticket $ticket) {

    }

    /**
     * Universell funksjon for å hente ID på et objekt oppgitt med navn
     * @param string    $entity     Objektnavnet, f.eks. 'status', '
     * @param string    $name       Navnet på enheten som skal finnes
     * @param bool      $useKey     Instruerer funksjonen til å lete i 'key' i stedet for 'name'
     *
     * @return mixed      ID for enheten eller null
     */
    public function getEntityId($entity, $name, $useKey = false) {
        $entity = Str::lower($entity);
        $uri = '/'.$entity.'/';
        $query = [];
        if ($useKey):
            $query['filter'] = 'key == "'.$name.'"';
        else:
            $query['filter'] = 'name == "'.$name.'"';
        endif;
        $entities = Str::plural($entity);
        if ($result = $this->apiQuery($uri, $query)):
            if (count($result[$entities])) return $result[$entities][0]['id'];
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
    public function getEntityByName($entity, $name, $useKey = false) {
        $entity = Str::lower($entity);
        $uri = '/'.$entity.'/';
        $query = [];
        if ($useKey):
            $query['filter'] = 'key == "'.$name.'"';
        else:
            $query['filter'] = 'name == "'.$name.'"';
        endif;
        $entities = Str::plural($entity);
        if ($result = $this->apiQuery($uri, $query)):
            if (count($result[$entities]) > 0) return $result[$entities][0];
        endif;
        return null; // Hvis ikke funnet
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
    public function fetchTypeIds() {
        // Henter ut relasjonstyper allerede i bruk i basen
        $uri = '/relationship/';
        $query = [
            'include' => 'type',
            'filter' => 'toAssetId!=null AND fromUserId!=null AND solvingRelationship == false'
        ];
        $result = $this->apiQuery($uri, $query);
        $relationshipTypes = collect($result['linked']['relationshiptypes']);
        $this->statuses = [];
        foreach(['computer', 'mobile'] as $type):
            // Henter ut ressurstypen basert på displayName
            $uri = '/assettype/';
            $query = [
                'filter' => 'name.equals("'.$this->myConf($type.'.displayName').'")',
                'include' => 'fields,statuses'
            ];
            $result = $this->apiQuery($uri, $query);
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
        $this->up = true;
    }

    /**
     * Henter ned standardinnstillinger for å opprette saker i PS
     *
     * Setter variabelen $this->ticketOptions
     */
    public function setTicketOptions(string $prefix = 'ticket') : void {
        $this->ticketOptions = [
            'zoneId' => $this->getEntityId('department', $this->myConf($prefix.'.zone')),
            'teamId' => $this->getEntityId('team', $this->myConf($prefix.'.team')),
            'sourceId' => $this->getEntityId('source', $this->myConf($prefix.'.source')),
            'requestTypeId' => $this->getEntityId('requesttype', $this->myConf($prefix.'.requestType'), true),
            'priorityId' => $this->getEntityId('priority', $this->myConf($prefix.'.priority')),
            'statusId' => $this->getEntityId('status', $this->myConf($prefix.'.status')),
            'ticketTypeId' => $this->getEntityId('tickettype', $this->myConf($prefix.'.ticketType')),
        ];
    }
    /**
     * Returnerer $this->ticketOptions
     */
    public function getTicketOptions() {
        return $this->ticketOptions;
    }

    /**
     * Henter alle datamaskin- og mobilenhet-ressurser fra Pureservice
     * @return  assoc_array     Array over ressursene
     */
    public function getAllAssets(): array {
        $totalAssets = [];
        foreach (['computer', 'mobile'] as $type):
            $uri = '/asset/';
            $query = [
                'filter' => 'typeID=='.$this->myConf($type.'.asset_type_id'),
            ];
            $assets = $this->apiQuery($uri, $query)['assets'];
            foreach ($assets as $asset):
                $asset['type'] = $type;
                $asset['usernames'] = $this->getAssetRelatedUsernames($asset['id']);
                $totalAssets[] = $asset;
            endforeach;
        endforeach;
        return $totalAssets;
    }

    public function getAssetRelatedUsernames(int $assetId): array {
        $relations_full = $this->getAssetRelationships($assetId);

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
     * Henter relasjoner for en gitt ressurs
     * @param string    $assetId    Ressursens ID
     *
     * @return assoc_array  Array over relasjonene knyttet til ressursen
     */
    public function getAssetRelationships($assetId) {
        $uri = '/relationship/' . $assetId .'/fromAsset';
        $query = [
            'include' => 'type,type.relationshipTypeGroup,toUser,toUser.emailaddress',
            'filter' => 'toUserId != NULL'
        ];
        return $this->apiQuery($uri, $query);
    }


    /**
     * Laster opp vedlegg til en sak i Pureservice
     * @param array         $attachments    Array over filstier relative til storage/app som skal lastes opp
     * @param App\Models\Ticket   $ticket   Saken som skal ha vedlegget
     *
     * @return assoc_array  Rapport på status og antall filer/opplastinger
     */
    public function uploadAttachments(array $attachments, Ticket $ticket, bool $connectToSolution = false): array {
        $uri = '/attachment';
        $attachmentCount = count($attachments);
        $uploadCount = 0;
        $status = 'OK';
        foreach ($attachments as $file):
            if (Storage::exists($file)):
                $filename = basename($file);
                $body = [
                    'name' => Str::beforeLast($filename, '.'),
                    'fileName' => $filename,
                    'size' => $this->human_filesize(Storage::size($file)),
                    'contentType' => Storage::mimeType($file),
                    'ticketId' => $ticket->id,
                    'bytes' => base64_encode(Storage::get($file)),
                    'isVisible' => true,
                ];
                if ($connectToSolution) $body['isPartOfCurrentSolution'] = true;
                // Sender med Content-Type satt til korrekt type
                if ($result = $this->apiPost($uri, $body, null, $this->myConf('api.accept'))):
                    $uploadCount++;
                else:
                    $status = 'Error ';
                endif;
            else:
                $status = 'Error for '.$file;
            endif;
        endforeach;
        return [
            'status' => $status,
            'fileCount' => $attachmentCount,
            'uploadCount' => $uploadCount,
        ];
    }

    public function solveWithAttachment(Ticket $ticket, string $solution, null|string $file = null) {
        // Finner ID for løst-status
        $statusId = $this->getEntityId('status', config('pureservice.dispatch.finishStatus', 'Løst'));

        // Sjekker at vedlegget eksisterer
        if (!$file || !Storage::exists($file)):
            if (!$ticket->pdf) $ticket->makePdf();
            $file = $ticket->pdf;
        endif;

        $uri = '/ticket/'.$ticket->id.'/';

        // return $this->apiPatch($uri, $body, 'application/json');
        $fileId = 'file-'.Str::ulid();

        $res = $this->apiGet($uri.'?include=attachments');

        $ticketData = $res['tickets'][0];
        if (!isset($ticketData['links']['attachments'])):
            $ticketData['links']['attachments'] = [];
        endif;
        if (isset($ticketData['links']['attachments']['ids'])):
            foreach ($ticketData['links']['attachments']['uids'] as $attach_id):
                $ticketData['links']['attachments'][] = [
                    'id' => $attach_id,
                    'type' => 'attachment',
                ];
            endforeach;
        endif;
        $ticketData['links']['attachments'][] = [
            'temporaryId' => $fileId,
            'type' => 'attachment',
        ];

        $ticketData['solution'] = $solution;
        // Setter sakens status
        $ticketData['statusId'] = $statusId;
        $ticketData['links']['status'] = [
            'id' => $statusId,
            'type' => 'status',
        ];

        $linked = isset($res['linked']) ? $res['linked'] : [];
        if (!isset($linked['attachments'])) $linked['attachments'] = [];

        unset($res);

        $linked['attachments'][] = [
            'name' => Str::beforeLast(basename($file), '.'),
            'fileName' => basename($file),
            'size' => $this->human_filesize(Storage::size($file)),
            'contentType' => Storage::mimeType($file),
            'bytes' => base64_encode(Storage::get($file)),
            'isVisible' => true,
            'ticketId' => $ticket->id,
            'temporaryId' => $fileId,
            'isPartofCurrentSolution' => true,
            'links' => [
                'ticket' => [
                    'type' => 'ticket',
                    'id' => $ticket->id,
                ],
            ],
        ];
        $body = [
            'tickets' => [
                $ticketData,
            ],
           'linked' => $linked,
        ];
        //dd($body);
        $uri .= '?include=communications,communications.attachments,communications.sender,recipientsCc,communications.recipients,communications.recipientsCc';
        return $this->apiPut($uri, $body);
    }
}
