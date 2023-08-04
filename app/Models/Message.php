<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;
use Illuminate\Support\Facades\{Storage, Blade};
use Illuminate\Support\{Str, Arr};
use App\Services\{PsApi, Enhetsregisteret};
use App\Models\{Ticket, Company, User};

class Message extends Model
{
    use HasFactory;
    protected $fillable = [
        'sender_id',
        'receiver_id',
        'documentId',
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

    /**
     * Oppgir nedlastingslokasjon for meldingen
    */
    public function downloadPath(bool $fullPath = false): string {
        $path = config('eformidling.path.download').'/'.$this->messageId;
        Storage::makeDirectory($path);
        return $fullPath ? Storage::path($path) : $path;
    }
    /**
     * Oppgir temp-lokasjon for meldingen
    */
    public function tempPath(bool $fullPath = false): string {
        $path = config('eformidling.path.temp').'/'.$this->messageId;
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
            $ps->setTicketOptions();
        endif;
        $sender = Company::find($this->sender_id);
        // Dersom avsender ikke er oss selv
        if ($sender->organizationNumber != config('eformidling.address.sender_id')):
            // Legg til eller oppdater avsenders virksomhet i Pureservice
            $sender->addOrUpdatePS($ps);
            // Legg til eller oppdater avsenders eFormidling-bruker i Pureservice
            $senderUser = $sender->users()->firstWhere('email', $sender->getEformidlingEmail())->addOrUpdatePs($ps);
        endif;
        $receiver = Company::find($this->receiver_id);
        // Dersom mottaker ikke er oss selv (noe det vil være)
        if ($receiver->organizationNumber != config('eformidling.address.sender_id')):
            $receiver->addOrUpdatePS($ps);
            $receiverUser = $receiver->users()->firstWhere('email', $sender->getEformidlingEmail())->addOrUpdatePs($ps);
        endif;

        $subject = Str::ucfirst($this->documentType());
        $subject .= ' for prosessen '.$this->processIdentifier;
        $description = '<ul>'.PHP_EOL;
        $description .= '  <li>Opprettet: '.$this->createdDtHr().'</li>'.PHP_EOL;
        $description .= '  <li>Forventet svardato: '.$this->expectedResponseDtHr().'</li>'.PHP_EOL;
        $description .= '  <li>Antall vedlegg: '.count($this->attachments).'</li>'.PHP_EOL;
        $description .= '</ul>'.PHP_EOL;
        $description .= '<p>Se vedlegg for innholdet i forsendelsen.</p>';

        if ($ticket = $ps->createTicket($subject, $description, $senderUser->id, config('pureservice.visibility.invisible'))):
            if (count($this->attachments)):
                $attachmentReport = $ps->uploadAttachments($this->attachments, $ticket);
            endif;
        endif;
        return $ticket;
    }
}
