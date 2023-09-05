<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Carbon\Carbon;
use Illuminate\Support\Facades\{Storage, Blade};
use Illuminate\Support\{Str, Arr, Collection};
use App\Services\{PsApi, Enhetsregisteret, Tools};
use App\Models\{Ticket, Company, User};

class Message extends Model {
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
        'messageId',
        'emailtext',
        //'has_lock',
    ];

    protected $casts = [
        'attachments' => 'array',
        'content' => 'array',
    ];

    protected $hidden = [
        'emailtext',
    ];
    /**
     * Standardverdier ved oppretting
     */
    // protected $attributes = [
    //     'attachment' => '[]',
    //     'content' => '[]',
    // ];

    public function sender(): Company|null {
        return Company::find($this->sender_id);
    }
    public function receiver(): Company|null {
        return Company::find($this->receiver_id);
    }

    public function getResponseDt(): string {
        return Carbon::now()->addDays(30)->toRfc3339String();
    }

    public function documentType() {
        return Arr::get($this->content, 'standardBusinessDocumentHeader.documentIdentification.type');
    }

    /**
     * Oppretter innholdet (hodet) til meldingen og lagrer i $this->content
     */
    public function createContent(string|false $template = false) {

        $content = json_decode(file_get_contents(resource_path(config('eformidling.out.template'))), true);

        Arr::set($content, 'standardBusinessDocumentHeader.sender.0.identifier.value', $this->sender()->actorId());
        //Arr::set($content, 'standardBusinessDocumentHeader.sender.0.authority', 'iso6523-actorid-upis');

        Arr::set($content, 'standardBusinessDocumentHeader.receiver.0.identifier.value', $this->receiver()->actorId());
        //Arr::set($content, 'standardBusinessDocumentHeader.receiver.0.authority', 'iso6523-actorid-upis');

        Arr::set($content, 'standardBusinessDocumentHeader.documentIdentification.standard', $this->documentStandard);
        Arr::set($content, 'standardBusinessDocumentHeader.documentIdentification.instanceIdentifier', $this->messageId);
        Arr::set($content, 'standardBusinessDocumentHeader.documentIdentification.creationDateAndTime', $this->getOpprettetDato());

        Arr::set($content, 'standardBusinessDocumentHeader.businessScope.scope.0.instanceIdentifier', $this->conversationId);
        Arr::set($content, 'standardBusinessDocumentHeader.businessScope.scope.0.identifier', $this->processIdentifier);

        // Forventet svartid
        $svarTid = Carbon::now(config('app.timezone'))->addDays(30)->toAtomString();
        Arr::set($content, 'standardBusinessDocumentHeader.businessScope.scope.0.scopeInformation.0.expectedResponseDateTime', $svarTid);
        $this->content = $content;
        $this->save();
    }

    /** Setter 'arkivmelding.hoveddokument' i $this->content */
    public function setMainDocument(string|false $file = false): void {
        if ($file) $this->mainDocument = basename($file);
        $content = $this->content;
        Arr::set($content, 'arkivmelding.hoveddokument', $this->mainDocument);
        $this->content = $content;
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
        $path = config('eformidling.path.download').'/'. $this->messageId;
        Storage::makeDirectory($path);
        return $fullPath ? Storage::path($path) : $path;
    }
    /**
     * Oppgir temp-lokasjon for meldingen
    */
    public function tempPath(bool $fullPath = false): string {
        $path = config('eformidling.path.temp').'/'. $this->messageId;
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
        $sender = $this->sender();
        // Dersom avsender ikke er oss selv
        if ($sender->organizationNumber != config('eformidling.address.sender_id')):
            // Legg til eller oppdater avsenders virksomhet i Pureservice
            $sender->addOrUpdatePS($ps);
            // Legg til eller oppdater avsenders eFormidling-bruker i Pureservice
            $senderUser = $sender->getEfUser();
            $senderUser->addOrUpdatePs($ps, true);
        endif;
        $receiver = $this->receiver();
        // Dersom mottaker ikke er oss selv (noe det vil være)
        if ($receiver->organizationNumber != config('eformidling.address.sender_id')):
            $receiver->addOrUpdatePS($ps);
            $receiverUser = $receiver->getEfUser();
            $receiverUser->addOrUpdatePs($ps, true);
        endif;

        if (Str::lower($this->documentType()) == 'arkivmelding' && $arkivmelding = $this->readXml()):
            $subject = Arr::get($arkivmelding, 'mappe.tittel', 'Ukjent emne');
            $description = Blade::render(config('eformidling.in.arkivmelding'), ['subject' => $subject, 'msg' => $this, 'arkivmelding' => $arkivmelding]);
        else:
            $subject = Str::ucfirst($this->documentType());
            $subject .= ' for prosessen '.$this->processIdentifier;
            $description = Blade::render(config('eformidling.in.arkivmelding'), ['subject' => $subject, 'msg' => $this]);
        //dd($description);
        endif;
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
        $emailtext = [];
        foreach ($this->attachments as $a):
            if (basename($a) == 'order.xml' ):
                $bestilling = json_decode(json_encode(simplexml_load_file(Storage::path($a))), true);
                // Rydder opp i tolkingen av xml
                $dokumenter = collect($bestilling['dokumenter']['dokument']);
                unset($bestilling['dokumenter']['dokument']);
                $bestilling['dokumenter'] = $dokumenter;
                unset($dokumenter);
            elseif (basename($a) == 'emailtext'):
                // Leser inn e-posttekst fra eInnsyn
                $docMetadata = $this->processEmailText(Storage::get($a));
            else:
                continue;
            endif;
        endforeach;
        if (!isset($bestilling)):
            return false;
        endif;
        // Innsynskrav kommer alltid fra Digdir, så vi må finne korrespondansepartneren fra bestillingen
        $senderUser = $this->userFromKontaktinfo($bestilling, $ps);
        $saker = $bestilling['dokumenter']->unique('saksnr');
        if (isset($saker['saksnr'])):
            // Det er bare ett dokument for én sak
            $saker = collect([$bestilling['dokumenter']->all()]);
        endif;
        $tickets = [];
        foreach ($saker as $sak):
            $saksnr = $sak['saksnr'];
            $subject = 'Innsynskrav for sak '. $sak['saksnr'];
            $description = Blade::render(config('eformidling.in.innsynskrav'), ['bestilling' => $bestilling, 'saksnr' => $saksnr, 'subject' => $subject, 'docMetadata' => $docMetadata]);
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
            if ($kontaktinfo['navn'] != '' && $kontaktinfo['navn'] != ' ' && !is_array($kontaktinfo['navn'])):
                $userData['firstName'] = Str::before($kontaktinfo['navn'], ' ');
                $userData['lastName'] = Str::after($kontaktinfo['navn'], ' ');
            else:
                $emailData = Tools::nameFromEmail($kontaktinfo['e-post']);
                $userData['firstName'] = $emailData[0];
                $userData['lastName'] = $emailData[1];
            endif;
            $user = User::factory()->create($userData);
            $user->save();
        endif;
        $user->addOrUpdatePS($ps);

        return $user;
    }

    /**
     * Oppretter arkivmelding.xml og vedlegger den til meldingen
     */
    public function createXmlFromTicket(Ticket|false $t = false): bool {
        if (!$t):
            $t = Ticket::factory()->create(['eformidling' => true, 'subject' => 'Sakens tittel', 'description' => 'Beskrivelse']);
        endif;
        $file = $this->tempPath().'/arkivmelding.xml';
        if (Storage::put($file, Blade::render('xml/arkivmelding', ['msg' => $this, 'ticket' => $t]))):
            $att = $this->attachments;
            $att[] = $file;
            $this->attachments = $att;
            $this->save();
            return true;
        endif;
        return false;
    }

    /**
     * Leser inn arkivmelding.xml
     */
    public function readXml(): array|false {
        $xmlfile = $this->downloadPath().'/arkivmelding.xml';
        if (Storage::fileExists($xmlfile)):
            $xmlData = json_decode(json_encode(simplexml_load_file(Storage::path($xmlfile))), true);
            return $xmlData;
        endif;
        return false;
    }

    /**
     * Sørger for at $this->attachments faktisk er et array
     */
    public function assureAttachments(): void {
        $tmp = is_array($this->attachments) ? $this->attachments : [];
        $this->save();
    }

    public function processEmailText(string $text): Collection {
        $dokText = Str::after($text, 'Dokumenter:');
        $dokArray = explode('--------------------------------------'.PHP_EOL, $dokText);
        $dokumenter = [];
        $template = [
            'saksnr' => '',
            'dokumentnr' => '',
            'sekvensnr' => '',
            'saksnavn' => '',
            'dokumentnavn' => '',
        ];
        foreach ($dokArray as $dok):
            $dokument = $template;
            $dokument['saksnr'] = trim(Str::before(Str::after($dok, 'Saksnr: '), ' | Dok nr'));
            $dokument['dokumentnr'] = trim(Str::before(Str::after($dok, 'Dok nr. : '), ' | Sekvensnr'));
            $dokument['sekvensnr'] = trim(Str::before(Str::after($dok, 'Sekvensnr.: '), PHP_EOL));
            $dokument['saksnavn'] = trim(Str::before(Str::after($dok, 'Sak: '), PHP_EOL));
            $dokument['dokumentnavn'] = trim(Str::before(Str::after($dok, 'Dokument: '), PHP_EOL));
            $dokumenter[] = $dokument;
        endforeach;

        return collect($dokumenter);
    }
}
