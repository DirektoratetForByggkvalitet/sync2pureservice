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

    public function getAndStoreCompany(string $regno): Company|false {
        if ($company = $this->apiGet('/enheter/'.$regno)):
            $fields = [
                'name' => Str::replace(' Og ', ' og ', Str::replace(' I ', ' i ', Str::title(Str::squish($company['navn'])))),
                'organizationNumber' => Str::squish($company['organisasjonsnummer']),
                'website' => isset($company['hjemmeside']) ? Str::squish($company['hjemmeside']) : null,
                'category' => config('pureservice.company.categoryMap.'.Arr::get($company, 'organisasjonsform.kode'), null),
                'email' => null,
                'phone' => null,
            ];
            $newCompany = Company::factory()->make($fields);
            return $newCompany;
        endif;
        return false;
    }
}
