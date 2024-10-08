<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Client\{Response};
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, BelongsToMany};

use App\Services\{PsApi, Tools};

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
        'email',
        'id'
    ];

    public function company(): BelongsTo {
        return $this->belongsTo(Company::class, 'id', 'companyId');
    }

    public function tickets(): BelongsToMany {
        return $this->belongsToMany(Ticket::class);
    }

    /**
     * Henter en bruker fra Pureservice, basert på ID eller e-postadresse
     * Dersom brukeren allerede ligger i lokal database blir ikke Pureservice kontaktet
     */
    public static function createFromPs(string|int $search, PsApi|null $ps = null): User|null {
        if ($res = self::firstWhere('id', $search) || $res = self::firstWhere('email', $search)) return $res;
        if (!$ps) $ps = new PsApi();
        return $ps->getCompanyOrUser($search);
    }

    /**
     * Synker brukeren med Pureservice
     * @param Pureservice $ps   Pureservice-instans
     * @param bool $noEmail     Angir om brukeren skal ha noemail-feltet satt til 1
     */
    public function addOrUpdatePS(PsApi $ps, bool $noEmail = false): User|array {
        $update = false;
        if ($psUser = $ps->findUser($this->email)):
            // Brukeren finnes i Pureservice
            if (
                $this->firstName != $psUser['firstName'] ||
                $this->lastName != $psUser['lastName'] ||
                $this->type != $psUser['type'] ||
                $this->notificationScheme != $psUser['notificationScheme'] ||
                $psUser['companyId'] != $this->companyId
            ):
                $update = true;
            endif;
            $this->id = $psUser['id'];
            $this->role = $psUser['role'];
            $this->companyId = $this->companyId ? $this->companyId : $psUser['companyId'];
            $emailDomain = Str::after($this->email, '@');
            // Søker etter kobling til foretak dersom companyId er tom og domenet ikke er i svartelisten
            if (!$this->companyId && !in_array($emailDomain, config('pureservice.domain_disable'))):
                if ($company = $ps->findCompanyByDomainName($emailDomain, true)):
                    if ($company->id != $this->companyId):
                        $this->companyId = $company->id;
                    endif;
                endif;
            endif;

            $this->save();
        endif;

        $emailId = $this->email ? $ps->findOrCreateEmailaddressId($this->email): null;

        $body = $this->toArray();
        if (isset($body['id'])) unset($body['id']);
        $body['emailaddressId'] = $emailId;
        if ($noEmail && config('pureservice.user.no_email_field')):
            $body[config('pureservice.user.no_email_field')] = 1;
        endif;

        if ($update):
            // Oppdaterer brukeren i Pureservice
            $uri = '/user/'.$psUser['id'];
            //$body['id'] = $psUser['id'];
            if ($ps->apiPatch($uri, $body, null, true)):
                return $this;
            endif;
            //return $ps->apiPut($uri, $body, null, true);
        endif;

        if (!$psUser):
            // Oppretter brukeren i Pureservice
            $uri = '/user';
            $postBody = ['users' => []];
            $postBody['users'][] = $body;
            //unset($body);
            $response = $ps->apiPost($uri, $body, config('pureservice.api.contentType'));
            if ($response->successful() && $response->json('users.0')):
                    $this->id = $response->json('users.0.id');
                    $this->save();
                return $this;
            else:
                return $response->json();
            endif;
        endif;
        return $this;
    }

}
