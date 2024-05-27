<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\{Http, Cache};
use Illuminate\Support\Str;

class JamfPro extends API {
    public bool $up = false;
    public string $version = '2.0';
    public string $errorMsg = '';

    public function __construct() {
        parent::__construct();
        $this->up = isset($this->token);
    }

    /**
     * Logger inn mot JamfPro for å hente id-token til bruk videre.
     */
    protected function setToken(): void {
        // Dersom token finnes sjekker vi den mot utløpstiden
        if (isset($this->token) && isset($this->tokenExpiry)):
            $in15minutes = Carbon::now(config('app.timezone'))->addMinutes(15);
            if ($this->tokenExpiry instanceof Carbon  && $this->tokenExpiry->isBefore($in15minutes)):
                unset($this->token, $this->tokenExpiry);
            endif;
        endif;
        // Setter ny token hvis token ikke er satt fra før
        if (!isset($this->token)):
            $request = Http::withBasicAuth(config('jamfpro.api.username'), config('jamfpro.api.password'));
            $request->baseUrl($this->base_url);
            $request->acceptJson();
            $uri = '/v1/auth/token';
            $response = $request->post($uri, null);
            if ($response->successful()):
                $this->token = $response->json('token', );
                $this->tokenExpiry = Carbon::parse($response->json('Expiry'), config('app.timezone'));
            else:
                dd($response);
            endif;
        endif;
    }

    /**
     * Henter alle maskiner fra Jamf Pro
     */
    public function getComputers() {
        $uri = '/v1/computers-inventory';
        $params = [
            'section' => 'GENERAL,HARDWARE,USER_AND_LOCATION,OPERATING_SYSTEM',
            'sort' => 'id:asc'
        ];
        return $this->paginatedQuery($uri, $params);
    }

    public function getMobileDevices($detailed = true) {
        $uri = '/v2/mobile-devices/detail';
        $params = [
            'section' => 'GENERAL,USER_AND_LOCATION,HARDWARE',
            'sort' => 'mobileDeviceId:asc'
        ];
        $results = $this->paginatedQuery($uri, $params);
        if ($detailed && !isset($results[0]['general']['initialEntryTimestamp'])):
            /**
             * Må løpe gjennom hver enkelt for å hente ut initialEntryTimestamp
             */
            $detailedResults = [];
            foreach ($results as $device):
                $detail = $this->apiGet('/v2/mobile-devices/'.$device['mobileDeviceId'].'/detail');
                $device['general']['initialEntryTimestamp'] = $detail['initialEntryTimestamp'];
                $detailedResults[] = $device;
            endforeach;
            return $detailedResults;
        endif;

        return $results;
    }

    /**
     * Smart funksjon som bruker paginering til å hente ut alle poster fra Jamf Pro
     */
    protected function paginatedQuery(string $uri, array $params = []): array {
        $results = [];
        $gotAll = false;
        $page=0;
        $page_size=100;

        $params['page-size'] = $page_size;
        while (!$gotAll):
            $params['page'] = $page;
            $response = $this->apiQuery($uri, $params, true);
            $results = array_merge($results, $response->json('results'));
            $gotAll = $response->json('totalCount') <= $page_size * ($page + 1);
            $page++;
        endwhile;

        return $results;
    }

}
