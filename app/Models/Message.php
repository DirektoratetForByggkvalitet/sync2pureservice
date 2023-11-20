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
 * ###########################
 * #### FELLES-funksjoner ####
 * ###########################
 */
    /**
     * Hent ut firmaet som er avsender
     */
    public function sender(): Company|null {
        return Company::find($this->sender_id);
    }

    /**
     * Hent ut firmaet som er mottaker
     */
    public function receiver(): Company|null {
        return Company::find($this->receiver_id);
    }

    /**
     * Oppretter en svardato som er 30 dager i fremtiden
     */
    public function getResponseDt(): string {
        return Carbon::now()->addDays(30)->toRfc3339String();
    }

    /**
     * Henter dokumenttypen fra content
     */
    public function documentType() {
        return Arr::get($this->content, 'standardBusinessDocumentHeader.documentIdentification.type');
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
     * Sørger for at $this->attachments faktisk er et array
     */
    public function assureAttachments(): void {
        $tmp = is_array($this->attachments) ? $this->attachments : [];
        $this->attachments = $tmp;
        $this->save();
    }

    /**
     * Legger en fil til som vedlegg til meldingen
     */
    protected function addToAttachments(array|string $additions): void {
        $attachments = $this->attachments;
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


/**
 * ##########################
 * #### UTGÅENDE melding ####
 * ##########################
 */

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

    /**
     * Oppretter arkivmelding.xml og vedlegger den til meldingen
     */
    public function createXmlFromTicket(Ticket|false $t = false): bool {
        if (!$t):
            $t = Ticket::factory()->create(['eformidling' => true, 'subject' => 'Sakens tittel', 'description' => 'Beskrivelse']);
        endif;
        $file = $this->tempPath().'/arkivmelding.xml';
        if (Storage::put($file, Blade::render('xml/arkivmelding', ['msg' => $this, 'ticket' => $t]))):
            $this->addToAttachments($file);
            return true;
        endif;
        return false;
    }


/**
 * #############################
 * #### INNKOMMENDE melding ####
 * #############################
 */
    public function getCreatedDtHr(): string {
        if ($dt = Arr::get($this->content, 'standardBusinessDocumentHeader.documentIdentification.creationDateAndTime')):
            return Carbon::parse($dt)->locale('nb')->isoFormat('LLLL');
        endif;
        return 'ikke oppgitt';
    }

    public function getExpectedResponseDtHr(): string {
        if ($dt = Arr::get($this->content, 'standardBusinessDocumentHeader.businessScope.scope.0.scopeInformation.0.expectedResponseDateTime')):
            return Carbon::parse($dt)->locale('nb')->isoFormat('LLLL');
        endif;
        return 'ikke oppgitt';

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
    public function saveToPs(PsApi|false $ps = false, bool $addAttachments = true): Ticket|false {
        if (!$ps):
            $ps = new PsApi();
            $ps->setTicketOptions('eformidling');
        endif;
        $sender = $this->sender();
        if ($sender->organizationNumber != config('eformidling.address.sender_id')):
            // Legg til eller oppdater avsenders virksomhet i Pureservice
            $sender->addOrUpdatePS($ps);
        else:
            $sender = $ps->getSelfCompany();
            $sender->save();
        endif;
            // Legg til eller oppdater avsenders eFormidling-bruker i Pureservice
        $senderUser = $sender->getEfUser();
        $senderUser->addOrUpdatePs($ps, true);
        $senderUser->syncChanges();

        // Dersom mottaker ikke er oss selv (noe det vil være)
        $receiver = $this->receiver();
        if ($receiver->organizationNumber != config('eformidling.address.sender_id')):
            $receiver->addOrUpdatePS($ps);
        else:
            $receiver = $ps->getSelfCompany();
            $receiver->save();
        endif;
        $receiverUser = $receiver->getEfUser();
        $receiverUser->addOrUpdatePs($ps, true);
        $receiverUser->syncChanges();

        if (Str::lower($this->documentType()) == 'arkivmelding' && $arkivmelding = $this->readXml()):
            $subject = Arr::get($arkivmelding, 'mappe.tittel', Arr::get($arkivmelding, 'mappe.basisregistrering.tittel', ''));
            if ($subject == ''):
                $subject = 'Emne ikke oppgitt';
            endif;
            $description = Blade::render(config('eformidling.in.arkivmelding'), ['subject' => $subject, 'msg' => $this, 'arkivmelding' => $arkivmelding]);
        else:
            $subject = Str::ucfirst($this->documentType());
            $subject .= ' for prosessen '.$this->processIdentifier;
            $description = Blade::render(config('eformidling.in.arkivmelding'), ['subject' => $subject, 'msg' => $this]);
        //dd($description);
        endif;
        if ($ticket = $ps->createTicket($subject, $description, $senderUser->id, config('pureservice.visibility.invisible'))):
            if (count($this->attachments) && $addAttachments):
                // Oppretter en kommunikasjon med vedleggene som vedlegg
                $ps->addCommunicationToTicket($ticket, $senderUser->id, $this->attachments);
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
        $emailtext = '';
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
                $emailtext = Storage::get($a);
                $docMetadata = $this->processEmailText($emailtext);
            else:
                continue;
            endif;
        endforeach;
        if (!isset($bestilling)):
            return false;
        endif;
        // Innsynskrav kommer alltid fra Digdir, så vi må finne korrespondansepartneren fra bestillingen
        $senderUser = $this->userFromKontaktinfo($bestilling, $ps);
        if (!$senderUser->id):
            $senderUser->addOrUpdatePS($ps);
            $senderUser->syncChanges();
        endif;
        //dd($senderUser);
        $bDokumenter = $bestilling['dokumenter'];
        $saker = $bestilling['dokumenter']->unique('saksnr');
        if (isset($saker['saksnr'])):
            // Det er bare ett dokument for én sak
            $tmp = [];
            $tmp[] = $bestilling['dokumenter']->all();
            $saker = collect($tmp);
            $bestilling['dokumenter'] = $saker;
            unset($tmp);
        endif;
        //dd($saker->all());
        $tickets = [];
        foreach ($saker as $sak):
            $saksnr = $sak['saksnr'];
            $subject = 'Innsynskrav for sak '. $sak['saksnr'];
            $description = Blade::render(config('eformidling.in.innsynskrav'), ['bestilling' => $bestilling, 'saksnr' => $saksnr, 'subject' => $subject, 'docMetadata' => $docMetadata]);
            $ticket = $ps->createTicket($subject, $description, $senderUser->id, config('pureservice.visibility.no_receipt'), true);
            // Oppretter en skjult innkommende melding med den opprinnelige bestillingen
            if (isset($emailtext)):
                $emailtext = Str::replace("\n", "<br/>\n", $emailtext);
                $emailtext = "<p><strong>Denne saken er en del av følgende bestilling fra eInnsyn</strong></p>\n\n" . $emailtext;
                $ps->addCommunicationToTicket(
                    $ticket,
                    $this->sender_id,
                    false,
                    config('pureservice.comms.description'),
                    config('pureservice.comms.direction.in'),
                    config('pureservice.comms.visibility.off'),
                    'eInnsyn: Opprinnelig bestilling',
                    $emailtext
                );
                //$ps->createInternalNote(Str::replace("\n", "<br/>\n", $emailtext), $ticket['id'], 'Opprinnelig bestilling', false);
            endif;
            $tickets[] = $ticket;
        endforeach;
        return $tickets;
    }

    protected function userFromKontaktinfo(array $bestilling, PsApi $ps): User {
        $kontaktinfo = &$bestilling['kontaktinfo'];
        if (!$user = User::firstWhere('email', $kontaktinfo['e-post'])):
            $userData = [
                'email' => trim($kontaktinfo['e-post']),
            ];
            if ($kontaktinfo['navn'] != '' && $kontaktinfo['navn'] != ' ' && !is_array($kontaktinfo['navn'])):
                $userData['firstName'] = Str::before($kontaktinfo['navn'], ' ');
                $userData['lastName'] = Str::after($kontaktinfo['navn'], ' ');
            else:
                $emailData = Tools::nameFromEmail(trim($kontaktinfo['e-post']));
                $userData['firstName'] = $emailData[0];
                $userData['lastName'] = $emailData[1];
            endif;
            $user = User::factory()->create($userData);
            $user->save();
        endif;
        $user->addOrUpdatePS($ps);
        $user->syncChanges();

        return $user;
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
