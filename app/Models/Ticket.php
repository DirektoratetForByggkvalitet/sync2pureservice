<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, BelongsToMany, HasMany, HasOne};
use Illuminate\Support\{Arr, Str, Collection};
//use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Ticket extends Model
{
    use HasFactory;

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
        'eForsendelse',
        'action',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
        'internal_id',
        'eForsendelse',
        'action',
    ];

    protected $properties = [
        'eForsendelse' => false,
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
}
