<?php

namespace App\Services;
use App\Services\API;
use App\Models\Company;
use Illuminate\Support\{Str, Arr};

class Enhetsregisteret extends API {

    public function __construct() {
        parent::__construct();
        $this->prefix = $this->myConf('api.prefix');
    }

    public function getCompany(string $regno): Company|false {
        $regno = Str::squish(Str::remove(' ', $regno));
        // Hvis foretaket allerede finnes i DB trenger vi ikke oppslag
        if ($found = Company::firstWhere('organizationNumber', $regno)):
            return $found;
        endif;
        if ($company = $this->apiGet($regno)):
            $fields = [
                'name' => Str::replace(' For ' , ' for ', Str::replace(' Og ', ' og ', Str::replace(' I ', ' i ', Str::title(Str::squish($company['navn']))))),
                'organizationNumber' => $regno,
                'website' => isset($company['hjemmeside']) ? Str::squish($company['hjemmeside']) : null,
                'category' => config('pureservice.company.categoryMap.'.Arr::get($company, 'organisasjonsform.kode'), null),
                'email' => null,
                'phone' => null,
            ];
        elseif ($regno == '987464291'):
            /**
             * Digitaliseringsdirektoratet sitt test-integrasjonspunkt finnes ikke i BRREG-APIet
             */
            $fields = [
                'name' => 'Digitaliseringsdirektoratet testsystem',
                'organizationNumber' => $regno,
                'website' => null,
                'category' => null,
                'email' => 'ikke_svar@einnsyn.no',
                'phone' => null,
            ];
        endif;
        if (isset($fields)):
            $c = Company::factory()->make($fields);
            $c->save();
            return $c;
        endif;
        return false;
    }
}
