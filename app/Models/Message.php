<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;
use Illuminate\Support\Facades\{Storage, Blade};

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

    public function getResponseDt() {
        return Carbon::now()->addDays(30)->toRfc3339String();
    }

    public function renderContent(string|false $template = false) {
        if (!$template) $template = config('eformidling.out.template');
        $this->content = Blade::render($template, ['message' => $this]);
        $this->save();
    }

}
