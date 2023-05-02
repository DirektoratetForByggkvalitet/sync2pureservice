<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Services\Pureservice;

class Company extends Model
{
    use HasFactory;
    protected $fillable = [
        'name',
        'organizationalNumber',
        'companyNumber',
        'website',
        'email',
        'phone',
        'notes'
    ];

    public function users(): HasMany {
        return $this->hasMany(User::class);
    }

    /**
     * Legger til virksomheten i Pureservice
     */
    public function addToOrUpdatePS() : array|false {
        $ps = new Pureservice();
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
        if ($this->phone && $emailId == null):
            $uri = '/companyemailaddress/';
            $body = [
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
                if (count($result['companies']) > 0) return true;
            endif;
        endif;

        return false;
    }

    public function addToOrUpdateUsersPS(): bool {
        return false;
    }
}
