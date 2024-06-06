<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PsEmail extends Model {
    use HasFactory;

    protected $primaryKey = 'dbid';
    protected $hidden = [
        'created',
        'modified',
        'modifiedBy',
        'createdBy',
        'dbid',
        'id'
    ];
    protected $fillable = [
        'id',
        'requestId',
        'assetId',
        'from',
        'fromName',
        'to',
        'cc',
        'bcc',
        'messageId',
        'subject',
        'text',
        'inReplyTo',
        'references',
        'emailDataId',
        'attachmentStrategy',
        'direction',
        'channelId',
        'status',
        'statusDate',
        'statusMessage',
        'statusDetails',
        'isInitial',
        'isSystem',
        'isBoundary',
        'created',
        'modified',
        'createdBy',
        'modifiedBy',
    ];
     protected $casts = [
        'modified' => 'datetime',
        'created' => 'datetime',
        'isInitial' => 'boolean',
        'isBoundary' => 'boolean',
        'statusDate' => 'datetime', 
     ];
}
