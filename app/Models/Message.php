<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;
use Illuminate\Support\Facades\{Storage, Blade};
use Illuminate\Support\{Str, Arr};
use App\Services\{PsApi, Enhetsregisteret, Tools};
use App\Models\{Ticket, Company, User};

class Message extends Model
{
    use HasFactory;
    protected $fillable = [
        'sender_id',
        'receiver_id',
        'documentStandard',
        'conversationId',
        'processIdentifier',
        'content',
        'mainDocument',
        'attachments',
    ];

    protected $casts = [
        'attachments' => 'array',
        'content' => 'array',
        'id' => 'string',
    ];

    public function getResponseDt() {
        return Carbon::now()->addDays(30)->toRfc3339String();
    }

    public function documentType() {
        return Arr::get($this->content, 'standardBusinessDocumentHeader.documentIdentification.type');
    }

    public function renderContent(string|false $template = false) {
        if (!$template) $template = config('eformidling.out.template');
        $this->content = Blade::render($template, ['message' => $this]);
        $this->save();
    }


    protected function createdDtHr(): string {
        if ($dt = Arr::get($this->content, 'standardBusinessDocumentHeader.documentIdentification.creationDateAndTime')):
            return Carbon::parse($dt)->locale('nb')->isoFormat('LLLL');
        endif;
        return 'ikke oppgitt';
    }

    protected function expectedResponseDtHr(): string {
        if ($dt = Arr::get($this->content, 'standardBusinessDocumentHeader.businessScope.scope.0.scopeInformation.0.expectedResponseDateTime')):
            return Carbon::parse($dt)->locale('nb')->isoFormat('LLLL');
        endif;
        return 'ikke oppgitt';

    }

    public function getOpprettetDato(): string {
        return Tools::atomTs();
    }
    public function getArkivertDato(): string {
        return $this->getOpprettetDato();
    }
    public function getCreatedDTLocalString(): string {
        return Carbon::now(config('app.timezone'))->toDateTimeLocalString();
    }

    public function getOpprettetAv(): string {
        return 'sync2pureservice';
    }
    public function getArkivertAv(): string {
        return $this->getOpprettetAv();
    }
    /**
     * Oppgir nedlastingslokasjon for meldingen
    */
    public function downloadPath(bool $fullPath = false): string {
        $path = config('eformidling.path.download').'/'. $this->id;
        Storage::makeDirectory($path);
        return $fullPath ? Storage::path($path) : $path;
    }
    /**
     * Oppgir temp-lokasjon for meldingen
    */
    public function tempPath(bool $fullPath = false): string {
        $path = config('eformidling.path.temp').'/'. $this->id;
        Storage::makeDirectory($path);
        return $fullPath ? Storage::path($path) : $path;
    }

    /**
     * Henter ut en lesbar businessScope.scope.identifier
     */
    public function processReadable(): string {
        $processes = [];
        foreach (Arr::get($this->content, 'standardBusinessDocumentHeader.businessScope.scope') as $scope):
            $processes[] = $scope['identifier'];
        endforeach;
        return implode(', ', $processes);
    }
     /**
     * Oppretter en sak i Pureservice basert på meldingen
     */
    public function saveToPs(PsApi|false $ps = false): Ticket|false {
        if (!$ps):
            $ps = new PsApi();
            $ps->setTicketOptions('eformidling');
        endif;
        $sender = Company::find($this->sender_id);
        // Dersom avsender ikke er oss selv
        if ($sender->organizationNumber != config('eformidling.address.sender_id')):
            // Legg til eller oppdater avsenders virksomhet i Pureservice
            $sender->addOrUpdatePS($ps);
            // Legg til eller oppdater avsenders eFormidling-bruker i Pureservice
            $senderUser = $sender->getEfUser()->addOrUpdatePs($ps);
        endif;
        $receiver = Company::find($this->receiver_id);
        // Dersom mottaker ikke er oss selv (noe det vil være)
        if ($receiver->organizationNumber != config('eformidling.address.sender_id')):
            $receiver->addOrUpdatePS($ps);
            $receiverUser = $receiver->getEfUser()->addOrUpdatePs($ps);
        endif;

        $subject = Str::ucfirst($this->documentType());
        $subject .= ' for prosessen '.$this->processIdentifier;
        $description = Blade::render('arkivmelding', ['subject' => $subject, 'msg' => $this]);
        //dd($description);

        if ($ticket = $ps->createTicket($subject, $description, $senderUser->id, config('pureservice.visibility.invisible'))):
            if (count($this->attachments)):
                $attachmentReport = $ps->uploadAttachments($this->attachments, $ticket);
            endif;
        endif;
        return $ticket;
    }

    /**
     * Splitter innsynskrav opp slik at hver sak får sitt eget innsynskrav
     */
    public function splittInnsynsKrav(PsApi|false $ps = false): array|false {
        if (!$ps):
            $ps = new PsApi();
            $ps->setTicketOptions('innsynskrav');
        endif;

        if (count($this->attachments) > 0):
            foreach ($this->attachments as $a):
                if (Storage::mimeType($a) == 'text/xml'):
                    $bestilling = json_decode(json_encode(simplexml_load_file(Storage::path($a))), true);
                    // Rydder opp i tolkingen av xml
                    $dokumenter = collect($bestilling['dokumenter']['dokument']);
                    unset($bestilling['dokumenter']['dokument']);
                    $bestilling['dokumenter'] = $dokumenter;
                    unset($dokumenter);
                else:
                    continue;
                endif;
            endforeach;
        endif;
        if (!isset($bestilling)):
            return false;
        endif;
        // Innsynskrav kommer alltid fra Digdir, så vi må finne korrespondansepartneren fra bestillingen
        $senderUser = $this->userFromKontaktinfo($bestilling, $ps);
        $saker = $bestilling['dokumenter']->unique('saksnr');
        $tickets = [];
        foreach ($saker as $sak):
            $saksnr = $sak['saksnr'];
            $subject = 'Innsynskrav for sak '. $sak['saksnr'];
            $description = Blade::render('innsynskrav', ['bestilling' => $bestilling, 'saksnr' => $saksnr, 'subject' => $subject]);
            $tickets[] = $ps->createTicket($subject, $description, $senderUser->id, config('pureservice.visibility.no_receipt'));
        endforeach;
        return $tickets;
    }

    protected function userFromKontaktinfo(array $bestilling, PsApi $ps): User {
        $kontaktinfo = &$bestilling['kontaktinfo'];
        if (!$user = User::firstWhere('email', $kontaktinfo['e-post'])):
            $userData = [
                'email' => $kontaktinfo['e-post'],
            ];
            if ($kontaktinfo['navn'] != ''):
                $userData['firstName'] = Str::before($kontaktinfo['navn'], ' ');
                $userData['lastName'] = Str::after($kontaktinfo['navn'], ' ');
            else:
                $emailData = Tools::nameFromEmail($kontaktinfo['e-post']);
                $userData['firstName'] = $emailData[0];
                $userData['lastName'] = $emailData[1];
            endif;
            $user = User::factory()->create($userData);
        endif;
        $user->addOrUpdatePS($ps);

        return $user;
    }

}
