<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PsStatus extends Model {
    use HasFactory;

    protected $primaryKey = 'dbid';

    protected $fillable = [
        'id',
        'name',
        'userDisplayName',
        'index',
        'disabled',
        'default',
        'requestTypeId',
        'requestTypeKey',
        'coreStatus'
    ];
}
