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
        'organizationNumber',
        'companyNumber',
        'website',
        'email',
        'phone',
        'notes',
        'category',
        'externalId',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
        'id',
        'externalId',
        'email',
        'phone',
        'category',
    ];

    public function users(): HasMany {
        return $this->hasMany(User::class);
    }

    /**
     * Synkroniserer virksomheten i Pureservice
     */
    public function addOrUpdatePS(Pureservice $ps) : bool {
        $update = false;

        if ($psCompany = $ps->findCompany($this->organizationNumber, $this->name)):
            $this->externalId = $psCompany['id'];
            $this->save();
            if (
                $this->organizationNumber != $psCompany['organizationNumber'] ||
                $this->email != data_get($psCompany, 'linked.companyemailaddresses.0.email') ||
                $this->companyNumber != $psCompany['companyNumber'] ||
                $this->website != $psCompany['website'] ||
                $this->notes != $psCompany['notes']
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
        ];
        if (config('pureservice.company.categoryfield', false)) $body[config('pureservice.company.categoryfield')] = $this->category;
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

}
