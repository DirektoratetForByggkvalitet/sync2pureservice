<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{HasMany, BelongsToMany};
use App\Services\{PsApi, Pureservice, Tools};
class Company extends Model {
    use HasFactory;
    protected $primaryKey = 'internal_id';
    protected $fillable = [
        'name',
        'organizationNumber',
        'companyNumber',
        'website',
        'email',
        'phone',
        'notes',
        'category',
        'id',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
        'internal_id',
        'email',
        'phone',
        'category',
    ];

    public function users(): HasMany {
        return $this->hasMany(User::class, 'companyId', 'id');
    }

    public function tickets(): BelongsToMany {
        return $this->belongsToMany(Ticket::class);
    }

    public function actorId() {
        return config('eformidling.address.prefix').$this->organizationNumber;
    }

    /**
     * Synkroniserer virksomheten i Pureservice
     */
    public function addOrUpdatePS(PsApi $ps) : bool {
        $update = false;
        $emailId = false;
        $phoneId = false;
        if ($psCompany = $ps->findCompany($this->organizationNumber, $this->name)):
            $this->id = $psCompany['id'];
            $this->save();
            $psCompany['email'] = data_get($psCompany, 'linked.companyemailaddresses.0.email');
            if ($psCompany['email'] && !$this->email) $this->email = $psCompany['email'];
            $emailId = $psCompany['emailAddressId'];
            $phoneId = $psCompany['phonenumberId'];
            // Finner ut om vi skal oppdatere virksomheten i PS
            if (
                $this->organizationNumber != $psCompany['organizationNumber'] ||
                $this->email != $psCompany['email'] ||
                $this->companyNumber != $psCompany['companyNumber'] ||
                $this->website != $psCompany['website'] ||
                $this->notes != $psCompany['notes']
            ):
                $update = true;
            endif;
        endif;

        if ($this->phone && !$phoneId):
            if ($phoneId = $ps->findPhonenumberId($this->phone)):
                // Fant eksisterende telefonnr
            else:
                $uri = '/phonenumber/';
                $body = [];
                $body['phonenumbers'][] = [
                    'number' => $this->phone,
                    'type' => 2,
                ];
                if ($response = $ps->apiPost($uri, $body)):
                    $phoneId = $response->json('phonenumbers.0.id');
                endif;
            endif;
        endif;

        if ($this->email && !$emailId):
            if ($emailId = $ps->findEmailaddressId($this->email, true)):
                // Fant eksisterende e-postadresse
            else:
                // Oppretter e-postadressen i PS
                $uri = '/companyemailaddress/';
                $body = [];
                $body['companyemailaddresses'][] = [
                    'email' => $this->email,
                ];
                if ($response = $ps->apiPost($uri, $body)):
                    $emailId = $response->json('companyemailaddresses.0.id');
                endif;
            endif;
        endif;

        $body = $this->toArray();

        if (config('pureservice.company.categoryfield', false)) $body[config('pureservice.company.categoryfield')] = $this->category;
        if ($emailId != null) $body['emailAddressId'] = $emailId;
        if ($phoneId != null) $body['phonenumberId'] = $phoneId;

        if ($update):
            // Oppdaterer virksomheten i Pureservice
            $uri = '/company/'.$this->id;
            // $body['id'] = $this->id;
            if (isset($body['id'])) unset($body['id']);
            return $ps->apiPatch($uri, $body, null, true);
        endif;

        if (!$psCompany):
            // Oppretter virksomheten i Pureservice
            $uri = '/company';
            unset($body['id']);
            $postBody = ['companies' => [$body]];
            unset($body);
            if ($response = $ps->apiPost($uri, $postBody)):
                $companies = $response->json('companies');
                if (count($companies) > 0):
                    $this->id = $companies[0]['id'];
                    $this->save();
                    return true;
                endif;
            endif;
        endif;

        return false;
    }

    /**
     * Oppretter SvarUt og postmottak-brukere for virksomheten
     */
    public function createStandardUsers() : void {
        if ($this->id):
            if ($this->email):
                if ($postmottak = $this->users()->firstWhere('email', $this->email)):
                    // Postmottaket finnes allerede i databasen
                else:
                    $postmottak = $this->users()->create([
                        'firstName' => 'Postmottak',
                        'lastName' => $this->name,
                        'email' => $this->email,
                    ]);
                endif;
            endif; // $this->email

            $efUser = $this->getEfUser();
        endif; // $this->id
    }

    public function getEfUser(): User {
        if ($this->id):
            if (!$efUser = $this->users()->firstWhere('email', $this->getEformidlingEmail())):
                $efUser = $this->users()->create([
                    'firstName' => 'eFormidling',
                    'lastName' => $this->name,
                    'email' => $this->getEformidlingEmail(),
                ]);
                $efUser->save();
            endif;
        endif;
        return $efUser;
    }

    public function getSvarUtEmail() {
        return $this->organizationNumber.'@'.config('pureservice.user.dummydomain');
    }

    public function getEformidlingEmail() {
        return $this->organizationNumber.'@'.config('pureservice.user.ef_domain');
    }
}
