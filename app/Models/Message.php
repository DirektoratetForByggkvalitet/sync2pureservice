<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsToMany};
use Carbon\Carbon;
use App\Models\Ticket;


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

    public function senderCompanies(): BelongsToMany {
        return $this->belongsToMany(Company::class, 'company_message')->wherePivot('type', '=', 'sender');
    }
    public function receiverCompanies(): BelongsToMany {
        return $this->belongsToMany(Company::class, 'company_message')->wherePivot('type', '=', 'receiver');
    }

    public function companies(): BelongsToMany {
        return $this->belongsToMany(Company::class, 'company_message');
    }

    public function getResponseDt() {
        return Carbon::now()->addDays(30)->toRfc3339String();
    }

}
