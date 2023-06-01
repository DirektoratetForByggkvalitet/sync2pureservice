<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{HasMany};

class Message extends Model
{
    use HasFactory;

    public function senderCompanies(): HasMany {
        return $this->hasMany(Company::class);
    }
    public function receiverCompanies(): HasMany {
        return $this->hasMany(Company::class);
    }
}
