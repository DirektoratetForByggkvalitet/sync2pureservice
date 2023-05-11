<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TicketCommunication extends Model
{
    use HasFactory;

    protected $fillable = [
        'id',
        'ticketId',
        'text',
        'subject',
        'changeId',
    ];

    protected $hidden = [
        'created_at',
        'updated_ad',
        'internal_id',
    ];


    public function ticket() {
        return $this->belongsTo(Ticket::class, 'id', 'ticketId');
    }
}
