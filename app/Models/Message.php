<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsToMany};


class Message extends Model
{
    use HasFactory;

    public function senderCompanies(): BelongsToMany {
        return $this->belongsToMany(Company::class, 'company_messages')->wherePivot('type', '=', 'sender');
    }
    public function receiverCompanies(): BelongsToMany {
        return $this->belongsToMany(Company::class, 'company_messages')->wherePivot('type', '=', 'receiver');
    }

    public function companies(): BelongsToMany {
        return $this->belongsToMany(Company::class, 'company_messages');
    }

}
