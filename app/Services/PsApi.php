<?php

namespace App\Services;
use App\Models\{Ticket, TicketCommunication, User, Company, Message};
use Carbon\Carbon;
use Illuminate\Support\{Str, Arr};
use Illuminate\Support\Facades\Storage;
use cardinalby\ContentDisposition\ContentDisposition;
use Illuminate\Http\Client\Response;

/**
 * Versjon 2 av Pureservice API-klient, basert på Laravel sitt HTTP Client-bibliotek
 */
class PsApi extends API {
    protected array $ticketOptions;
    protected array $statuses;

    public function __construct() {
        $this->cKey = 'pureservice';
        $this->setProperties();
    }

    /**
     * Universell funksjon som henter ut et objekt fra Pureservice basert på IDnr
     * @param string $entity    Objektnavnet, f.eks. 'status'
     * @param int $id           ID-nr for objektet
     */
    public function getEntityById(string $entity, int $id): array|null {
        $entity = Str::lower($entity);
        $entities = Str::plural($entity);
        $uri = '/'.$entity.'/'.$id;
        $query = [
            'filter' => '!disabled',
        ];
        if ($result = $this->apiQuery($uri, $query)):
            if (count($result[$entities])) return $result[$entities][0];
        endif;
        return null;
    }
    /**
     * Universell funksjon for å hente et objekts navn basert på ID
     * @param string $entity    Objektnavnet, f.eks. 'status'
     * @param int $id           ID-nr for objektet
     * @param string $nameField Hvilket felt som skal hentes som navnefelt, standard = 'name'
     */
    public function getEntityNameById(string $entity, int $id, string $nameField = 'name'): string {
        $result = $this->getEntityById($entity, $id);
        return isset($result[$nameField]) ? $result[$nameField] : 'Ukjent';
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
    public function getEntityByName(string $entity, string $name, bool $useKey = false): array|null {
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
     * Sletter en relasjon i Pureservice
     * @param   string  $relationshipId     Relasjonen sin ID
     *
     * @return  bool    angir om slettingen lyktes eller ikke
     */
    public function deleteRelation($relationshipId) {
        return $this->apiDelete('/relationship/'.$relationshipId.'/delete');
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
     * Oppretter sak med gitt emne og beskrivelse med oppgitt brukerId som sluttbruker
     * @param   string  $subject        Sakens emne
     * @param   string  $description    Beskrivelse av saken
     * @param   array   $userId         Sluttbrukers ID
     * @param   mixed   $visibility     Synlighetskode, standard = 2 (usynlig)
    */
    public function createTicket(string $subject, string $description, int $userId, int $visibility = 2, bool $returnClass = true): array|false|Ticket {
        //if ($this->ticketOptions == []) $this->setTicketOptions();
        $uri = '/ticket';
        $ticket = [
            'subject' => $subject,
            'description' => $description,
            'userId' => $userId,
            'visibility' => $visibility,
            'assignedDepartmentId' => $this->ticketOptions['zoneId'],
            'assignedTeamId' => $this->ticketOptions['teamId'],
            'sourceId' => $this->ticketOptions['sourceId'],
            'ticketTypeId' => $this->ticketOptions['ticketTypeId'],
            'priorityId' => $this->ticketOptions['priorityId'],
            'statusId' => $this->ticketOptions['statusId'],
            'requestTypeId' => $this->ticketOptions['requestTypeId'],
        ];
        $body = ['tickets' => [$ticket]];
        //dd($body);
        $response = $this->apiPost($uri, $ticket);
        if ($response->successful()):
            $t = collect($response->json('tickets'));
            if ($returnClass):
                return $t->mapInto(Ticket::class)->first();
            else:
                return $t->first();
            endif;
        // else:
        //     return $response->json();
        endif;
        return false;
    }


    /**
     * Laster opp vedlegg til en sak i Pureservice
     * @param array         $attachments    Array over filstier relative til storage/app som skal lastes opp
     * @param App\Models\Ticket   $ticket   Saken som skal ha vedlegget
     * @param array         $communication  Vedleggene skal koblet til en kommunikasjon
     *
     * @return assoc_array  Rapport på status og antall filer/opplastinger
     */
    public function uploadAttachments(array $attachments, Ticket $ticket, bool $connectToSolution = false): array {
        $uri = '/attachment';
        $attachmentCount = count($attachments);
        $uploadCount = 0;
        $status = 'OK';
        $uploads = [];
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
                $result = $this->apiPost($uri, $body, null, $this->myConf('api.accept'));
                if ($result->successful()):
                    $uploads[] = $result->json('attachments.0');
                else:
                    $status = 'Feil med '.$file;
                endif;
            else:
                $status = 'Filen \''.$file.'\' ble ikke funnet';
            endif;
        endforeach;
        return [
            'status' => $status,
            'fileCount' => $attachmentCount,
            'uploadCount' => count($uploads),
            'uploads' => $uploads,
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

    /**
     * Finner en bruker basert på e-postadressen.
     * Siden én bruker kan ha flere e-postadresser er det viktig at man søker etter e-postadresse-objekter,
     * og inkluderer brukeren, fremfor å søke etter brukeren.
     */
    public function findUser($email, bool $returnClass = false): array|false {
        $uri = '/emailaddress/';
        $query = [
            'include' => 'user',
            'filter' => 'email == "'.$email.'"',
        ];
        if ($result = $this->apiQuery($uri, $query)):
            if (count($result['emailaddresses']) > 0):
                if (!isset($result['linked'])) return false;
                $user = $result['linked']['users'][0];
                return $returnClass ? collect($user)->mapInto('App\Models\User') : $user;
            endif;
        endif;
        return false;
    }

    public function findCompany(string|null $orgNo=null, string|null $companyName=null, bool $returnClass = false): Company|array|false {
        $uri = '/company/';
        $query = [
            'include'=> 'phonenumber,emailAddress',
        ];
        if ($orgNo != null):
            $query['filter'] = '(!disabled AND organizationNumber=="'.$orgNo.'")';
            $companiesByOrgNo = $this->apiQuery($uri, $query)['companies'];
        else:
            $companiesByOrgNo = [];
        endif;

        if ($companyName != null):
            $query['filter'] = '(!disabled AND name=="'.$companyName.'")';
            $companiesByName = $this->apiQuery($uri, $query)['companies'];
        else:
            $companiesByName = [];
        endif;
        $companyByOrgNo = count($companiesByOrgNo) > 0 ? $companiesByOrgNo[0]: false;
        $companyByName = count($companiesByName) > 0 ? $companiesByName[0]: false;
        // Gitt at begge selskaper blir funnet, er de det samme?
        $sameCompany = ($companyByName && $companyByOrgNo) && ($companyByName['id'] == $companyByOrgNo['id']);
        if ($sameCompany || (!$companyByName && $companyByOrgNo)):
            $returnValue = &$companyByOrgNo;
        else:
            $returnValue = &$companyByName;
        endif;
        return $returnClass ? collect($returnValue)->mapInto('App\Models\Company'): $returnValue;
    }


    /**
     * Oppretter et firma i Pureservice, og legger til e-post og telefonnr hvis oppgitt
     * @param string|App\Models\Company $companyName   Foretaksnavn, påkrevd
     * @param string $orgNo         Foretakets organisasjonsnr. Viktig for at SvarInn-integrasjon skal virke
     * @param string $email         Foretakets e-postadresse
     * @param string $phone         Foretakets telefonnr
     *
     * @return mixed    array med det opprettede foretaket eller false hvis oppretting feilet
     */

    public function addCompany(string|Company $companyName, $orgNo = null, $email = false, $phone = false) : array|false {
        $useCompanyNameAsObject = false;
        if (is_a($companyName, 'App\Models\Company')):
            $useCompanyNameAsObject = true;
        endif;
        $phoneId = $phone ? $this->findPhonenumberId($phone): null;
        $emailId = $email ? $this->findEmailaddressId($email, true): null;
        if ($email && $emailId == null):
            $uri = '/companyemailaddress/';
            $body = [
                'email' => $email,
            ];
            if ($response = $this->apiPost($uri, $body)):
                $emailId = $response->json('companyemailaddresses.0.id');
            endif;
        endif;
        if ($phone && $phoneId == null):
            $uri = '/phonenumber/';
            $body = [];
            $body['phonenumbers'][] = [
                'number' => $phone,
                'type' => 2,
            ];
            if ($response = $this->apiPost($uri, $body)):
               $phoneId = $response->json('phonenumbers.0.id');
            endif;
        endif;

        // Oppretter selve foretaket
        $uri = '/company?include=phonenumber,emailAddress';
        if ($useCompanyNameAsObject):
            $body = [
                'name' => $companyName->name,
                'organizationNumber' => $companyName->organizationalNumber,
                'companyNumber' => $companyName->companyNumber,
                'website' => $companyName->website,
                'notes' => $companyName->notes,
            ];
        else:
            $body = [
                'name' => $companyName,
                'organizationNumber' => $orgNo
            ];
        endif;
        if ($emailId != null) $body['emailAddressId'] = $emailId;

        if ($phoneId != null) $body['phonenumberId'] = $phoneId;

        if ($response = $this->apiPost($uri, $body)):
            return $response->json('companies.0');
        endif;

        return false;
    }

    /**
     * Henter ID for e-postadresse registrert i Pureservice
     * @param string    $email          E-postadressen
     * @param bool      $companyAddress Angir om man skal se etter en firma-adresse
     *
     * @return int|null    null hvis den ikke finnes, IDen dersom den finnes.
     */
    public function findEmailaddressId($email, $companyAddress=false): int|null {
        $prefix = $companyAddress ? 'company' : '';
        $uri = '/'.$prefix.'emailaddress';
        $args = [
            'filter'=>'email == "'.$email.'"',
        ];

        if ($result = $this->apiQuery($uri, $args)):
            $found = count($result[$prefix.'emailaddresses']);
            if ($found > 0):
                return Arr::get($result, $prefix.'emailaddresses.0.id');
            endif;
        endif;
        return null; // Hvis ikke funnet
    }

    public function findOrCreateEmailaddressId($email, $companyAddress=false): int|null {
        $prefix = $companyAddress ? 'company' : '';
        $uri = '/'.$prefix.'emailaddress';
        $args = [
            'filter'=>'email == "'.$email.'"',
        ];
        $search = $this->apiQuery($uri, $args, true);
        if ($search->successful() && count($search->json($prefix.'emailaddresses')) > 0):
            return $search->json($prefix.'emailaddresses.0.id');
        else:
            $body = [
                $prefix.'emailaddresses' => [
                    [
                        'email' => $email,
                    ],
                ],
            ];
            $response = $this->apiPost($uri, $body, null, $this->myConf('api.contentType'));
            if ($response->successful()):
                return $response->json($prefix.'emailaddresses.0.id');
            else: // debug
                dd($response->json());
            endif;
        endif;

        return null;
    }

    /**
     * Henter ID for telefonnummer registrert i Pureservice
     * @param string    $phonenumber          E-postadressen
     * @param bool      $companyAddress Angir om man skal se etter en firma-adresse
     *
     * @return int|null    null hvis den ikke finnes, IDen dersom den finnes.
     */
    public function findPhonenumberId($phonennumber): int|null {
        //$prefix = $companyAddress ? 'company' : '';
        $uri = '/phonennumber';
        $args = [
            'filter' => 'email == "'.$phonennumber.'"',
        ];
        if ($result = $this->apiQuery($uri, $args)):
            $found = count($result['phonenumbers']);
            if ($found > 0):
                return Arr::get($result,'phonenumbers.0.id');
            endif;
        endif;
        return null; // Hvis ikke funnet
    }

    /**
     * Legger til brukere som er koblet til et foretak
     * Alle data ligger i DB
     */
    public function addCompanyUsers(Company $company): array|false {
        $body = ['users' => []];
        foreach ($company->users as $user):
            $emailId = $this->findOrCreateEmailaddressId($user->email);
            $record = [
                'firstName' => $user->firstName,
                'lastName' => $user->lastName,
                'role' => $user->role,
                'type' => $user->type,
                'notificationScheme' => $user->notificationScheme,
                'emailAddressId' => $emailId,
                'companyId' => $company->externalId,
            ];
            if (config('pureservice.user.no_email_field')) $record[config('pureservice.user.no_email_field')] = 1;
            $body['users'][] = $record;
        endforeach;
        // Oppretter brukerne
        $uri = '/user/?include=emailAddress,company';

        $response = $this->apiPost($uri, $body);
        if ($response->successful()):
            return $response->json('users.0');
        endif;
        return false;
    }

    public function createInternalNote(string $message, int $ticketId, string|null $subject = null): bool {
        $uri = '/communication/';
        $body = ['communications' => [
            [
                'text' => $message,
                'subject' => $subject,
                'type' => config('pureservice.comms.internal'),
                'direction' => config('pureservice.comms.direction.internal'),
                'ticketId' => $ticketId,
             ]
        ]];
        if ($res = $this->apiPOST($uri, $body)) return true;

        return false;
    }
    /**
     * Legger til en innkommende kommunikasjon på saken
     */
    public function addInboundCommunicationToTicket(Ticket $ticket, int $senderId, array|false $attachments = false): Response {
        $uri = 'communication/?include=sender';
        $body = [];
        $tempId = Str::uuid()->toString();
        $body['communications'] = [
            [
                'direction' => config('pureservice.comms.direction.in'),
                'type' => config('pureservice.comms.standard'),
                'subject' => $ticket->subject,
                'ticketId' => $ticket->id,
                'text' => $ticket->description,
                'links' => [
                    'sender' => [
                        'temporaryId' => $tempId,
                    ],
                ],
            ],
        ];
        $body['linked'] = [
            'communicationrecipients' => [
                [
                    'userId' => $senderId,
                    'temporaryId' => $tempId,
                    'type' => 'sender',
                ]
            ]
        ];
        if (is_array($attachments)):
            // if (isset($attachments['id'])) $attachments = [$attachments];
            // $body['communications'][0]['links']['attachments'] = [
            //     'ids' => data_get($attachments, '*.id'),
            //     'type' => 'attachment',
            // ];
            $body['linked']['attachments'] = [];
            foreach ($attachments as $file):
                if (basename($file) == 'arkivmelding.xml'):
                    continue;
                endif;
                if (Storage::exists($file)):
                    $filename = basename($file);
                    $attTempId = Str::uuid()->toString();
                    $body['communications'][0]['links']['attachments'][] = [
                        'temporaryId' => $attTempId,
                        'type' => 'attachment',
                    ];
                    $body['linked']['attachments'][] = [
                        'name' => Str::beforeLast($filename, '.'),
                        'fileName' => $filename,
                        'size' => $this->human_filesize(Storage::size($file)),
                        'contentType' => Storage::mimeType($file),
                        'ticketId' => $ticket->id,
                        'isVisible' => true,
                        'temporaryId' => $attTempId,
                        'bytes' => base64_encode(Storage::get($file)),
                    ];
                endif;
            endforeach;
        endif;
        // dd(json_encode($body, JSON_PRETTY_PRINT));
        return $this->apiPost($uri, $body, null, config('pureservice.api.accept'));
    }

}
