<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
        'internal_id',
        'internal_user_id',
    ];

    public function user() {
        return $this->hasOne(User::class, 'id', 'internal_user_id');
    }

    public function communications() {
        return $this->hasMany(Communication::class, 'ticketId', 'id');
    }
}
