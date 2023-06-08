<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, BelongsToMany};
use Illuminate\Support\Str;
use App\Services\{Pureservice, Tools};

class User extends Model
{
    use HasFactory;
    protected $primaryKey = 'internal_id';
    protected $fillable = [
        'firstName',
        'lastName',
        'email',
        'id',
        'companyId'
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
        'internal_id',
        'email'
    ];

    public function company(): BelongsTo {
        return $this->belongsTo(Company::class, 'id', 'companyId');
    }

    public function tickets(): BelongsToMany {
        return $this->belongsToMany(Ticket::class);
    }

    /** Synker bruker med Pureservice */
    public function addOrUpdatePS(Pureservice $ps): bool {
        //$psCompanyUsers = $ps->findUsersByCompanyId($this->company->externalId;);
        $update = false;
        if ($psUser = $ps->findUser($this->email)):
            // Brukeren finnes i Pureservice
            if (
                $this->firstName != $psUser['firstName'] ||
                $this->lastName != $psUser['lastName'] ||
                $this->role != $psUser['role'] ||
                $this->type != $psUser['type'] ||
                $this->notificationScheme != $psUser['notificationScheme'] ||
                $psUser['companyId'] != $this->companyId
            ):
                $update = true;
            endif;
            $this->id = $psUser['id'];
            $this->companyId = $psUser['companyId'];
            $this->save();
        endif;

        $emailId = $this->email ? $ps->findEmailaddressId($this->email): null;
        if ($this->email && $emailId == null):
            $uri = '/emailaddress/';
            $body = ['emailaddresses' => []];
            $body['emailaddresses'][] = [
                'email' => $this->email,
            ];
            if ($response = $ps->apiPOST($uri, $body)):
                $result = json_decode($response->getBody()->getContents(), true);
                $emailId = $result['emailaddresses'][0]['id'];
            endif;
        endif;


        $body = $this->toArray();
        if (config('pureservice.user.no_email_field')) $body[config('pureservice.user.no_email_field')] = 1;
        $body['companyId'] = $this->companyId;
        $body['emailAddressId'] = $emailId;

        if ($update):
            // Oppdaterer brukeren i Pureservice
            $uri = '/user/'.$psUser['id'];
            $body['id'] = $psUser['id'];
            return $ps->apiPut($uri, $body, true);
        endif;

        if (!$psUser):
            // Oppretter brukeren i Pureservice
            $uri = '/user';
            $postBody = ['users' => []];
            $postBody['users'][] = $body;
            unset($body);
            if ($response = $ps->apiPOST($uri, $postBody)):
                $result = json_decode($response->getBody()->getContents(), true);
                if (count($result['users']) > 0):
                    $this->id = $result['users'][0]['id'];
                    $this->save();
                    return true;
                endif;
            endif;
        endif;
    return false;
}

}