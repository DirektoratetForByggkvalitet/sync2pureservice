<?php

namespace App\Services;
use App\Models\{Ticket, TicketCommunication, User, Company};
use Carbon\Carbon;
use Illuminate\Support\{Str, Arr};
use Illuminate\Support\Facades\Storage;

/**
 * Versjon 2 av Pureservice sitt API, basert på Laravel sitt HTTP Client-bibliotek
 */
class PsApi extends API {
    protected array $ticketOptions;

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

    public function solveWithAttachment(Ticket $ticket, string $file, string $solution) {
        // Først laste opp vedlegget
        // $uri = '/attachment/';
        // $body = [
        //         'name' => Str::beforeLast(basename($file), '.'),
        //         'fileName' => basename($file),
        //         'size' => $this->human_filesize(Storage::size($file)),
        //         'contentType' => Storage::mimeType($file),
        //         'ticketId' => $ticket->id,
        //         'bytes' => base64_encode(Storage::get($file)),
        //         'isVisible' => true,
        //         'embedded' => false,
        // ];
        // if ($result = $this->apiPost($uri, $body, null, $this->myConf('api.accept')))
        //     $attachment = $result['attachments'][0];


        // $uri = '/ticket/'.$ticket->id.'/';
        // $res = $this->apiGet($uri);
        // $ticketData = $res['tickets'][0];

        // $ticketData['links']['attachments'] = [];
        // $linked = ['attachments' => []];
        // $statusId = $this->getEntityId('status', config('pureservice.dispatch.finishStatus', 'Løst'));
        // $fileId = 'file-'.Str::ulid();
        // $linked['attachments'][] = [
        //     'attachmentCopyId' => $attachment['id'],
        //     'name' => $attachment['name'],
        //     'fileName' => $attachment['fileName'],
        //     'contentId' => $attachment['contentId'],
        //     'contentLength' => $attachment['contentLength'],
        //     'contentType' => $attachment['contentType'],
        //     'ticketId' => $ticket->id,
        //     'isVisible' => false,
        //     'embedded' => false,
        //     'isPartofCurrentSolution' => true,
        //     'temporaryId' => $fileId,
        // ];
        // $ticketData['links']['attachments'][] = [
        //     'temporaryId' => $fileId,
        //     'type' => 'attachment',
        // ];
        // $ticketData['solution'] = $solution;
        // $ticketData['statusId'] = $statusId;
        // $body = [
        //     'tickets' => [
        //         $ticketData
        //     ],
        //     'linked' => $linked,
        // ];
        $uri = '/communication/?include=ticket';
        $fileId = 'file-'.Str::ulid();
        $body = ['linked' => [], 'communications' => []];
        $body['communications'][] = [
            'direction' => config('pureservice.comms.direction.out'),
            'ticketId' => $ticket->id,
            'type' => config('pureservice.comms.solution'),
            'subject' => $ticket->subject,
            'text' => $solution,
            'links' => [
                'attachments' => [
                    [
                        'temporaryId' => $fileId,
                    ],
                ],
            ]
        ];
        $body['linked']['attachments'] = [
            [
                'name' => Str::beforeLast($file, '.'),
                'fileName' => $file,
                'size' => $this->human_filesize(Storage::size($file)),
                'contentType' => Storage::mimeType($file),
                'ticketId' => $ticket->id,
                'bytes' => base64_encode(Storage::get($file)),
                'isVisible' => true,
                'isPartofCurrentSolution' => true,
                'temporaryId' => $fileId,
            ],
        ];

        return $this->apiPut($uri, $body, $this->myConf('api.accept'));
    }

}
