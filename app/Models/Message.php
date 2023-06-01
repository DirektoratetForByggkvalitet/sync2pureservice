<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, BelongsToMany};

class Message extends Model
{
    use HasFactory;

    public function sender() {
        return $this->belongsTo(Company::class);
    }
    public function receivers() {
        return $this->belongsToMany(Company::class);
    }
}
