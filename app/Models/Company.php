<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Services\{Pureservice, Tools};
class Company extends Model {
    use HasFactory;
    protected $fillable = [
        'name',
        'organizationalNumber',
        'companyNumber',
        'website',
        'email',
        'phone',
        'notes',
        'category',
        'externalId',
        'streetAddress',
        'city',
        'postalCode',
        'country',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
        'id',
        'externalId',
        'email',
        'phone',
        'category',
        'streetAddress',
        'city',
        'postalCode',
        'country',
    ];

    public function users(): HasMany {
        return $this->hasMany(User::class);
    }

    /**
     * Synkroniserer virksomheten i Pureservice
     */
    public function addToOrUpdatePS(Pureservice $ps) : bool {
        $update = false;

        if ($psCompany = $ps->findCompany($this->organizationNumber, $this->name)):
            $this->externalId = $psCompany['id'];
            $this->save();
            if (
                $this->organizationalNumber != $psCompany['organizationNumber'] ||
                $this->email != data_get($psCompany, 'linked.companyemailaddresses.0.email') ||
                $this->companyNumber != $psCompany['companyNumber'] ||
                $this->website != $psCompany['website'] ||
                $this->notes != $psCompany['notes'] ||
                $this->category != $psCompany[config('pureservice.company.categoryfield')]
            ):
                $update = true;
            endif;
        endif;
        $phoneId = $this->phone ? $ps->findPhonenumberId($this->phone): null;
        $emailId = $this->email ? $ps->findEmailaddressId($this->email, true): null;
        if ($this->email && $emailId == null):
            $uri = '/companyemailaddress/';
            $body = [];
            $body['companyemailaddresses'][] = [
                'email' => $this->email,
            ];
            if ($response = $ps->apiPOST($uri, $body)):
                $result = json_decode($response->getBody()->getContents(), true);
                $emailId = $result['companyemailaddresses'][0]['id'];
            endif;
        endif;
        if ($this->phone && $phoneId == null):
            $uri = '/phonenumber/';
            $body = [];
            $body['phonenumbers'][] = [
                'number' => $this->phone,
                'type' => 2,
            ];
            if ($response = $ps->apiPOST($uri, $body)):
                $result = json_decode($response->getBody()->getContents(), true);
                $phoneId = $result['phonenumbers'][0]['id'];
            endif;
        endif;

        $body = [
            'name' => $this->name,
            'organizationNumber' => $this->organizationNumber,
            'companyNumber' => $this->companyNumber,
            'website' => $this->website,
            'notes' => $this->notes,
            config('pureservice.company.categoryfield') => $this->category,
        ];
        if ($emailId != null) $body['emailAddressId'] = $emailId;
        if ($phoneId != null) $body['phonenumberId'] = $phoneId;

        if ($update):
            // Oppdaterer virksomheten i Pureservice
            $uri = '/company/'.$this->externalId;
            $body['id'] = $this->externalId;
            return $ps->apiPut($uri, $body, true);
        endif;

        if (!$psCompany):
            // Oppretter virksomheten i Pureservice
            $uri = '/company';
            $postBody = ['companies' => [$body]];
            unset($body);
            if ($response = $ps->apiPOST($uri, $postBody)):
                $result = json_decode($response->getBody()->getContents(), true);
                if (count($result['companies']) > 0):
                    $this->externalId = $result['companies'][0]['id'];
                    $this->save();
                    return true;
                endif;
            endif;
        endif;

        return false;
    }

    /** Synker virksomhetens brukere med Pureservice */
    public function addToOrUpdateUsersPS(Pureservice $ps): bool {
        //$psCompanyUsers = $ps->findUsersByCompanyId($this->externalId);
        foreach ($this->users as $user):
            $update = false;
            echo Tools::l2().$user->firstName.' '.$user->lastName."\n";
            if ($psUser = $ps->findUser($user->email)):
                // Brukeren finnes i Pureservice
                if (
                    $user->firstName != $psUser['firstName'] ||
                    $user->lastName != $psUser['lastName'] ||
                    $user->role != $psUser['role'] ||
                    $user->type != $psUser['type'] ||
                    $user->notificationScheme != $psUser['notificationScheme'] ||
                    $psUser[config('pureservice.user.no_email_field')] != 1 ||
                    $psUser['companyId'] != $this->externalId
                ):
                    $update = true;
                endif;
            endif;

            $emailId = $user->email ? $ps->findEmailaddressId($user->email): null;
            if ($user->email && $emailId == null):
                $uri = '/emailaddress/';
                $body = ['emailaddresses' => []];
                $body['emailaddresses'][] = [
                    'email' => $user->email,
                ];
                if ($response = $ps->apiPOST($uri, $body)):
                    $result = json_decode($response->getBody()->getContents(), true);
                    $emailId = $result['emailaddresses'][0]['id'];
                endif;
            endif;


            $body = $user->toArray();
            $body[config('pureservice.user.no_email_field')] = 1;
            $body['companyId'] = $this->externalId;
            $body['emailAddressId'] = $emailId;

            if ($update):
                // Oppdaterer brukeren i Pureservice
                $uri = '/user/'.$psUser['id'];
                $body['id'] = $psUser['id'];
                echo Tools::l3().'Oppdatert'."\n";
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
                        echo Tools::l3().'Opprettet'."\n";
                        return true;
                    endif;
                endif;
            else:
                echo Tools::l3().'Trenger ikke oppdatering'."\n";
            endif;
        endforeach;
        return false;
    }
}
