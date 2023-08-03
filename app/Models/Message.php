<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;
use Illuminate\Support\Facades\{Storage, Blade};
use Illuminate\Support\{Str, Arr};
use App\Services\{PsApi, Enhetsregisteret};

class Message extends Model
{
    use HasFactory;
    protected $fillable = [
        'sender_id',
        'sender',
        'receiver',
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

    public function renderContent(string|false $template = false) {
        if (!$template) $template = config('eformidling.out.template');
        $this->content = Blade::render($template, ['message' => $this]);
        $this->save();
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
     * Oppretter en Ticket::class basert på meldingen
     */
    public function toPsTicket(PsApi|false $ps = false) {
        if (!$ps):
            $ps = new PsApi();
            $ps->setTicketOptions();
        endif;

    }
}
