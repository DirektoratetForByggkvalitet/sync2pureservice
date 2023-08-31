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
        'ticketTypeId',
        'visibility',
        'emailAddress',
        'subject',
        'description',
        'solution',
        'eFormidling',
        'action',
        'pdf',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
        'internal_id',
        'eForsendelse',
        'action',
        'pdf'
    ];

    protected $properties = [
        'eForsendelse' => false,
        'pdf' => null,
    ];


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

    public function extractRecipientsFromAsset(PsApi|Pureservice $ps, array $recipientListAssetType) : void {

        $uri = '/relationship/'.$this->id.'/fromTicket';
        $query = [
            'include' => 'toAsset',
            'filter' => 'toAsset.typeId == '.$recipientListAssetType['id'],
        ];
        $relatedLists = $ps->apiQuery($uri, $query);
        if (count($relatedLists['relationships']) > 0):
            foreach ($relatedLists['linked']['assets'] as $list):
                // Henter ut mottakerlistens relaterte bruker og firma,
                // og kobler dem til saken som mottakere
                $uri = '/relationship/'.$list['id'].'/fromAsset';
                $query = [
                    'include' => 'toUser,toCompany,toUser.emailaddresses,toCompany.emailaddresses',
                    'filter' => 'toTicketId == null',
                ];
                if ($listRelations = $ps->apiQuery($uri, $query)):
                    // 1. Legger sluttbrukere som er relatert til mottakerlisten til saken
                    if (isset($listRelations['linked']['users']) && count($listRelations['linked']['users'])):
                        $users = collect($listRelations['linked']['users'])
                        ->mapInto(User::class)
                        ->each(function (User $user, int $key) use ($listRelations) {
                            if ($email = collect($listRelations['linked']['emailaddresses'])->firstWhere('userId', $user->id)):
                                $user->email = $email['email'];
                            endif;
                            if ($existing = User::firstWhere('id', $user->id)):
                                $user = $existing;
                            endif;
                            $user->save();
                            $this->recipients()->attach($user);
                        });
                        unset($users);
                    endif;

                    // 2. Legger firma som er relatert til mottakerlisten til saken
                    if (isset($listRelations['linked']['companies']) && count($listRelations['linked']['companies'])):
                        $companies = collect($listRelations['linked']['companies'])
                        ->mapInto(Company::class)
                        ->each(function (Company $company, int $key) use ($listRelations) : void {
                            if ($email = collect($listRelations['linked']['companyemailaddresses'])->firstWhere('companyId', $company->id)):
                                $company->email = $email['email'];
                            endif;
                            if ($existing = Company::firstWhere('id', $company->id)) $company = $existing;
                            $company->save();
                            $this->recipientCompanies()->attach($company);
                        });
                        unset($companies);
                    endif;
                endif; // $listRelations
            endforeach; // $relatedLists['linked']['assets'] as $list
        endif; // $relatedLists && count($relatedLists['relationships']

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
                $this->attachments = $filesToAttach;
                $this->save();
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

        $this->save();
        return $this->pdf;
    }

    public function getDownloadPath(bool $full = false): string {
        $path = config('pureservice.api.dlPath');
        $path .= '/'.$this->requestNumber;

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

    public function createMessage(Company $receiver) {
        $message = Message::factory()->make([
            'sender_id' => config('eformidling.address.sender_id'),
            'receiver_id' => $receiver->getIso6523ActorIdUpi(),
            'mainDocument' => !$this->pdf ? basename($this->pdf) : basename($this->makePdf()),
        ]);
        $message->attachments = Storage::allFiles($this->getDownloadPath());
        // Lagrer JSON for meldingshodet og lagrer i DB
        $message->makeContent();

        if ($message->documentType() == 'arkivmelding'):
            // Vi trenger en arkivmelding.xml-fil
            $xmlfile = $this->tempPath().'/arkivmelding.xml';
            if (Storage::put($xmlfile, Blade::render('xml/arkivmelding', ['ticket' => $this, 'msg' => $message]))):
                $message->attachments[] = $xmlfile;
            endif;
        endif;

    }

    /**
     * Returnerer status på saken fra Pureservice
     */
    public function getStatus(PsApi|null $ps = null): string {
        if ($this->statusId):
            if (!$ps) $ps = new PsApi();
            return $ps->getEntityNameById('status', $this->statusId, 'userDisplayName');
        endif;
        return "Ukjent";
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
}
