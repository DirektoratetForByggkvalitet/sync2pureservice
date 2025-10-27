<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, BelongsToMany, HasMany, HasOne};
use Illuminate\Support\{Arr, Str, Collection};
use App\Services\{Eformidling, Pureservice, PsApi};
use Illuminate\Support\Facades\{Storage, Blade};
use Barryvdh\DomPDF\Facade\Pdf as PDF;
use App\Models\{Message, Company};
//use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Ticket extends Model
{
    use HasFactory;
    protected $primaryKey = 'internal_id';

    protected $fillable = [
        'id',
        'requestNumber',
        'assignedAgentId',
        'assignedTeamId',
        'assignedDepartmentId',
        'userId',
        'priorityId',
        'statusId',
        'sourceId',
        'category1Id',
        'category2Id',
        'category3Id',
        'customerReference',
        'ticketTypeId',
        'emailAddress',
        'subject',
        'description',
        'visibility',
        'solution',
        'eFormidling',
        'action',
        'pdf',
        'links',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
        'internal_id',
        'eFormidling',
        'action',
        'pdf',
        'attachments',
        // Felt som skal inn i links
        'assignedAgentId',
        'assignedTeamId',
        'assignedDepartmentId',
        'userId',
        'priorityId',
        'statusId',
        'sourceId',
        'category1Id',
        'category2Id',
        'category3Id',
        'customerReference',
        'ticketTypeId',
        'emailAddress',
    ];

    protected $properties = [
        'eForsendelse' => false,
        'pdf' => null,
    ];

    protected function casts(): array {
        return [
            'attachments' => 'array',
            'links' => 'array',
        ];
    }

    public function user() : HasOne {
        return $this->hasOne(User::class, 'id', 'userId');
    }

    public function communications() : HasMany {
        return $this->hasMany(TicketCommunication::class, 'ticketId', 'id');
    }

    public function recipients() : BelongsToMany {
        return $this->belongsToMany(User::class);
    }

    public function recipientCompanies(): BelongsToMany {
        return $this->belongsToMany(Company::class);
    }

    /**
     * Returnerer sakens ID til e-postens emnefelt
     */
    public function getTicketSlug() : string {
        $replaceString = config('pureservice.ticket.codeTemplate');
        return Str::replace('{{RequestNumber}}', $this->requestNumber, $replaceString);
    }

    public function emailSubject() : string {
        return $this->subject . ' ' . $this->getTicketSlug();
    }

    /**
     * Returnerer array over mottakere som er koblet til mottakerlister
     */
    public function extractRecipientsFromAsset(PsApi $ps, array $recipientListAssetType, bool $returnList = false): Collection {
        $recipientList = [];
        $uri = '/relationship/'.$this->id.'/fromTicket';
        $query = [
            'include' => 'toAsset',
            'filter' => 'toAsset.typeId == '.$recipientListAssetType['id'],
        ];
        $response = $ps->apiQuery($uri, $query, true);
        if ($response->successful() && count($response->json('relationships'))):
            $lists = $response->json('linked.assets');
            unset($response);
            foreach ($lists as $l):
                $uri = '/relationship/'.$l['id'].'/fromAsset';
                $query = [
                    'include' => 'toUser,toCompany,toUser.emailaddresses,toCompany.emailaddresses',
                    'filter' => 'toTicketId == null',
                ];
                $response = $ps->apiQuery($uri, $query, true);
                if ($response->successful()):
                    $userEmails = collect($response->json('linked.emailaddresses'));
                    $users = collect($response->json('linked.users'))
                        ->mapInto(User::class)
                        ->each(function (User $user, int $key) use ($userEmails, &$recipientList){
                            if ($mail = $userEmails->firstWhere('userId', $user->id)):
                                $user->email = $mail['email'];
                            endif;
                            if ($existing = User::firstWhere('id', $user->id)):
                                $user = $existing;
                            endif;
                            $user->save();
                            $this->recipients()->attach($user);
                            $recipientList[] = $user;
                        });
                    $companyEmails = collect($response->json('linked.companyemailaddresses'));
                    $companies = collect($response->json('linked.companies'))
                        ->mapInto(Company::class)
                        ->each(function (Company $company, int $key) use ($companyEmails, &$recipientList) {
                            if ($mail = $companyEmails->firstWhere('companyId', $company->id)):
                                $company->email = $mail['email'];
                            endif;
                            if ($existing = Company::firstWhere('id', $company->id)) $company = $existing;
                            $company->save();
                            $this->recipientCompanies()->attach($company);
                            $recipientList[] = $company;
                        });
                endif;
            endforeach;
            return collect($recipientList);
        endif;
        return collect([]);
    }

    /**
     * Laster ned og tar vare på eventuelle vedlegg til saken
     */
    public function downloadAttachments(PsApi $ps) {
        $uri = '/attachment/';
        $query = [
            'filter' => 'ticketId == '.$this->id
        ];
        $res = $ps->apiGet($uri, false, null, $query);
        if (count($res['attachments'])):
            $dlPath = $this->getDownloadPath();
            $filesToAttach = [];
            foreach ($res['attachments'] as $att):
                $uri = '/attachment/download/'.$att['id'];
                $response = $ps->apiGet($uri, true, '*/*')->toPsrResponse();
                // Henter filnavn fra header content-disposition - 'attachment; filename="dokumenter-7104a48e.zip"'
                $fileName = explode('=', explode(';', $response->getHeader('content-disposition')[0])[1])[1];
                $filePath = $dlPath.'/'.$fileName;
                Storage::put($filePath, $response->getBody()->getContents());
                $filesToAttach[] = $filePath;
            endforeach;

            // Kobler vedlegget til saken i DB
            if (count($filesToAttach)):
                $this->addToAttachments($filesToAttach);
            endif;
        endif;

    }

    public function makePdf (string $view = 'message', $filename = 'Utgående melding') {
        $data = [
            'ticket' => $this,
            'includeFonts' => true,
        ];
        $pdf = PDF::loadView($view, $data);

        $this->pdf = $this->pdf ? $this->pdf : $this->getDownloadPath(true) . '/' .$filename. '.pdf';

        if (Storage::exists($this->pdf)) Storage::delete($this->pdf);
        $pdf->save($this->pdf, config('filesystems.default'));

        $this->addToAttachments($this->pdf);

        $this->save();
        return $this->pdf;
    }

    public function getDownloadPath(bool $full = false): string {
        $path = config('pureservice.api.dlPath');
        $path .= '/'.$this->requestNumber;

        Storage::createDirectory($path);

        return $full ? Storage::path($path): $path;
    }

    /**
     * Hvordan skal saken sendes ut?
     */
    public function decideAction() {
        switch ($this->emailAddress):
            case config('pureservice.dispatch.address_ef'):
                $this->eFormidling = true;
                break;
            default:
                $this->eFormidling = false;
        endswitch;
        $this->action = 'normalSend';
        $this->save();
    }

    /**
     * Oppretter en eFormidling-melding fra saken
     */
    public function createMessage(Company $receiver): Message {
        $message = Message::factory()->make([
            'sender_id' => config('eformidling.address.sender_id'),
            'receiver_id' => $receiver->getIso6523ActorIdUpi(),
            'mainDocument' => $this->pdf ? basename($this->pdf) : basename($this->makePdf()),
        ]);
        $message->addToAttachments(Storage::allFiles($this->getDownloadPath()));
        // Lagrer JSON for meldingshodet og lagrer i DB
        $message->makeContent();

        if ($message->documentType() == 'arkivmelding'):
            // Vi trenger en arkivmelding.xml-fil
            $xmlfile = $this->tempPath().'/arkivmelding.xml';
            if (Storage::put($xmlfile, Blade::render('xml/arkivmelding', ['ticket' => $this, 'msg' => $message]))):
                $message->addToAttachments($xmlfile);
            endif;
        endif;

        return $message;

    }

    /**
     * Returnerer status på saken fra Pureservice
     */
    public function getStatus(PsApi|null $ps = null): string {
        if ($this->statusId):
            if (!$ps) $ps = new PsApi();
            return $ps->getEntityNameById('status', $this->statusId);
        endif;
        return 'Ukjent';
    }
    /**
     * Setter status på saken til status oppgitt som tekst eller int (statusId)
     * Valgfri løsningstekst, hvis hensikten er å løse saken
     */
    public function changeStatus(PsApi|null $ps = null, string|null $status = null, string|null $solution = null): Ticket {
        if (!$ps) $ps = new PsApi();
        $status = $status ? $this->statusId : $ps->getEntityId('status', $status);
        $this->statusId = $status;
        $uri = '/ticket/'.$this->id;
        $body = [
            'statusId' => $status,
        ];
        // Oppgitt løsning overstyrer eventuell løsningstekst i saken
        if ($solution):
            $body['solution'] = $solution;
            $this->solution = $solution;
        elseif ($this->solution):
            $body['solution'] = $this->solution;
        endif;

        $response = $ps->apiPatch($uri, $body);

        return $this;
    }
    /**
     * Returnerer sakstype på saken fra Pureservice
     */
    public function getType(PsApi|null $ps = null): string {
        if ($this->ticketTypeId):
            if (!$ps) $ps = new PsApi();
            return $ps->getEntityNameById('tickettype', $this->ticketTypeId);
        endif;
        return "Ukjent";
    }

    /**
     * Henter kommunikasjon på saken fra Pureservice
     * Lagrer dem som TicketCommunication
     */
    public function getPsCommunications(PsApi|null $ps = null): false|array {
        if (!$this->id) return false;
        if (!$ps) $ps = new PsApi();
        $uri = '/communication/';
        $params = [
            'include' => 'attachments',
            'filter' => 'ticketId == '.$this->id.'',
            'sort' => 'created desc, modified desc',
        ];
        $response = $ps->apiQuery($uri, $params, true);
        if ($response->successful() && count($response->json('communications'))):
            $comms = collect($response->json('communications'))->mapInto(TicketCommunication::class);
            $attachments = collect($response->json('linked.attachments'));
            $comms->each(function(TicketCommunication $comm, int $key) use ($attachments) {
                $commAttachments = $attachments->where('links.');
            });
        endif;
        return false;
    }

    protected function addToAttachments(array|string $additions): void {
        $attachments = is_array($this->attachments) ? $this->attachments : [];

        if (!is_array($additions)) $additions = [$additions];
        $save_needed = false;
        foreach ($additions as $add):
            if (!in_array($add, $attachments)):
                $attachments[] = $add;
                $save_needed = true;
            endif;
        endforeach;
        if ($save_needed):
            $this->attachments = $attachments;
            $this->save();
        endif;
    }

    public function getChangeArray(PsApi|null $ps = null): array {
        $ps = $ps ? $ps : new PsApi();
        $psTicket = $ps->getTicketFromPureservice($this->id, false);
        $psArr = $psTicket->toArray();
        $myArr = $this->toArray();
        $myChanges = [];
        foreach (array_keys($myArr) as $field):
            if ($myArr[$field] != $psArr[$field]):
                $myChanges[$field] = $myArr[$field];
            endif;
        endforeach;
        return $myChanges;
    }
    /**
     * Legger til saken i Pureservice, eller oppdaterer eksisterende sak
     */
    public function addOrUpdatePS(PsApi $ps): Ticket|null {
        $add = $this->id ? false : true;

        if (!isset($this->links) || $this->links == []):
            $this->setLinksFromTicketOptions($ps);
        endif;
        $body = $this->toArray();
        unset($body['id'], $body['requestNumber']);
        //dd($body);
        $uri = '/ticket/';
        if ($add):
            $response = $ps->apiPost($uri, $body);
            if ($response->successful()):
                $this->id = $response->json('tickets.0.id');
                $this->requestNumber = $response->json('tickets.0.requestNumber');
                $this->save();
                return $this;
            else:
                $ps->error_json = $response->json();
            endif;
        else:
            $body = $this->getChangeArray($ps);
            //dd($body);
            if (count($body)):
                $uri .= $this->id;                
                $response = $ps->apiPatch($uri, $body, 'application/json', 'application/vnd.api+json');
                if ($response->successful()):
                    return $this;
                else:
                    $ps->error_json = $response->json();
                endif;
            else:
                return $this;
            endif;
        endif;
        return null;
    }

    // Henter inn innstillinger for saken fra valgene satt i PsApi
    public function setLinksFromTicketOptions(PsApi $ps) {
        $ticketOptions = $ps->getTicketOptions();
        $links = is_array($this->links) ? $this->links : [];
        foreach ($ticketOptions as $option => $value):
            $key = Str::before($option, 'Id');
            switch ($key) {
                case 'zone':
                    $key = 'assignedDepartment';
                    break;
                
                case 'team':
                    $key = 'assignedTeam';
                    break;
                default:
                    break;
            }
            $links[$key] = ['id' => $value];
        endforeach;
        if (isset($this->userId) && !isset($links['user'])):
            $links['user'] = ['id' => $this->userId];
        endif;
        $this->links = $links;
    }
}
