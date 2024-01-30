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
}
