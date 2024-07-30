<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\{Http, Cache};
use Illuminate\Support\Str;

class JamfPro extends API {
    public bool $up = false;
    private string $version = '2.1';
    public string $errorMsg = '';

    public function __construct() {
        parent::__construct();
        $this->up = $this->token ? true : false;
    }

    /**
     * Logger inn mot JamfPro for å hente id-token til bruk videre.
     */
    public function setToken(): void {
        // Dersom token finnes sjekker vi den mot utløpstiden
        if ($this->token && isset($this->tokenExpiry)):
            $in15minutes = Carbon::now(config('app.timezone'))->addMinutes(15);
            if ($this->tokenExpiry instanceof Carbon  && $this->tokenExpiry->isBefore($in15minutes)):
                $this->token = $this->tokenExpiry = false;
            endif;
        endif;
        // Setter ny token hvis token ikke er satt fra før
        if (!$this->token):
            $params = null;
            $oauth = config('jamfpro.api.client_id') ? true : false;
            if ($oauth):
                // Bruker API Client-innlogging: https://learn.jamf.com/en-US/bundle/jamf-pro-documentation-current/page/API_Roles_and_Clients.html
                $request = Http::asForm();
                $params = [
                    'client_id' => config('jamfpro.api.client_id'),
                    'grant_type' => 'client_credentials',
                    'client_secret' => config('jamfpro.api.client_secret'),
                ];
                $uri = 'oauth/token';

            else:
                // API v1 sin API-innlogging
                $request = Http::withBasicAuth(config('jamfpro.api.username'), config('jamfpro.api.password'));
                $uri = '/v1/auth/token';
            endif;
            $request->baseUrl($this->base_url);
            $request->acceptJson();
            $response = $request->post($uri, $params);
            if ($response->successful()):
                if ($oauth):
                    $this->token = $response->json('access_token');
                    $this->tokenExpiry = Carbon::now(config('app.timezone'))->addSeconds($response->json('expires_in'));
                else:
                    $this->token = $response->json('token');
                    $this->tokenExpiry = Carbon::parse($response->json('Expiry'), config('app.timezone'));
                endif;
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
             * Løper gjennom hver enkelt enhet for å hente ut initialEntryTimestamp
             */
            $detailedResults = [];
            foreach ($results as $device):
                $device['general']['initialEntryTimestamp'] = $this->getMobileDeviceInitialEntryTimestamp($device['mobileDeviceId']);
                $detailedResults[] = $device;
            endforeach;
            return $detailedResults;
        endif;

        return $results;
    }

    /**
     * Henter inn initialEntryTimestamp fra mobilenheten
     */
    public function getMobileDeviceInitialEntryTimestamp(int $id): string|null {
        $detail = $this->apiGet('/v2/mobile-devices/'.$id.'/detail');
        return $detail['initialEntryTimestamp'] ? $detail['initialEntryTimestamp'] : null;
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
