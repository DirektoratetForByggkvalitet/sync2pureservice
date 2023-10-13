<?php

namespace App\Services;
use App\Services\API;
use App\Models\Company;
use Illuminate\Support\{Str, Arr};
use Illuminate\Http\Client\{RequestException};

class Enhetsregisteret extends API {

    public function __construct() {
        parent::__construct();
    }

    public function getCompany(string $regno): Company|false {
        $regno = Str::squish(Str::remove(' ', $regno));
        // Hvis foretaket allerede finnes i DB trenger vi ikke oppslag
        if ($found = Company::firstWhere('organizationNumber', $regno)):
            if ($found->name == 'Virksomhet ikke i BRREG' && $company = $this->apiGet($regno)):
                $found->name = Str::replace(' For ' , ' for ', Str::replace(' Og ', ' og ', Str::replace(' I ', ' i ', Str::title(Str::squish($company['navn'])))));
            endif;
            return $found;
        endif;
        if ($company = $this->lookupCompany($regno)):
            $fields = [
                'name' => Str::replace(' For ' , ' for ', Str::replace(' Og ', ' og ', Str::replace(' I ', ' i ', Str::title(Str::squish($company['navn']))))),
                'organizationNumber' => $regno,
                'website' => isset($company['hjemmeside']) ? Str::squish($company['hjemmeside']) : null,
                'category' => config('pureservice.company.categoryMap.'.Arr::get($company, 'organisasjonsform.kode'), null),
                'email' => null,
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

    public function lookupCompany(string $regno): array|false {
        foreach (['enheter', 'underenheter'] as $uri):
            $uri .= '/'.$regno;
            try {
                $response = $this->apiGet($uri, true);
                if ($response->successful()):
                    return $response->json();
                endif;
            } catch (RequestException $e) {
                //return $e->response;
                continue;
            }
        endforeach;
        return false;
    }
}
