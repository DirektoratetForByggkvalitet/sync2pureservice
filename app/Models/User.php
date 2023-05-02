<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use App\Services\Pureservice;

class User extends Model
{
    use HasFactory;
    protected $fillable = [
        'firstName',
        'lastName',
        'email',
    ];

    protected $attributes = [
        'role' => 10,
        'notificationScheme' => 1,
        'type' => 0,
        'password' => '90873ojkjkksajk23å0909jujcsdoij',
    ];

    protected $hidden = [
        'email_verified_at',
        'remember_token',
        'password',
        'created_at',
        'updated_at',
        'id',
        'company_id',
        'email',
    ];

    public function company(): BelongsTo {
        return $this->belongsTo(Company::class);
    }
}
