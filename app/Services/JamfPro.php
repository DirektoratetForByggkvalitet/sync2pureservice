<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\{Http, Cache};
use Illuminate\Support\Str;

class JamfPro extends API {
    public bool $up = false;
    public string $version = "2.0";

    public function __construct() {
        $this->setCKey(Str::lower(class_basename($this)));
        $this->setProperties();
        $this->setToken();
        $this->up = isset($this->token);
    }

    protected function setToken(): void {
        if (isset($this->token) && isset($this->tokenExpiry)):
            $in15minutes = Carbon::now(config('app.timezone'))->addMinutes(15);
            if ($this->tokenExpiry instanceof Carbon  && $this->tokenExpiry->isBefore($in15minutes)):
                unset($this->token, $this->tokenExpiry);
            endif;
        endif;
        if (!isset($this->token)):
            $request = Http::withUserAgent($this->myConf('api.user-agent', config('api.user-agent')));
            // Setter timeout for forespørselen
            $request->timeout($this->myConf('api.timeout', config('api.timeout')));
            $request->retry($this->myConf('api.retry', config('api.retry')));
            // Setter headers
            $request->withHeaders([
                'Connection' => $this->myConf('api.headers.connection', config('api.headers.connection')),
                'Accept-Encoding' => $this->myConf('api.headers.accept-encoding', config('api.headers.accept-encoding')),
            ]);
            $request->baseUrl($this->myConf('api.url').$this->prefix);
            $request->acceptJson();
            $request->withBasicAuth($this->myConf('api.username'), $this->myConf('api.password'));
            $uri = '/v1/auth/token';
            $response = $request->post($uri, '');
            if ($response->successful()):
                $this->token = $response->json('token');
                $this->tokenExpiry = Carbon::parse($response->json('Expiry'), config('app.timezone'));
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
