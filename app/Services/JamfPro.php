<?php

namespace App\Services;

use Carbon\Carbon;
use Hamcrest\Type\IsObject;
use Illuminate\Support\Facades\{Http, Cache};

class JamfPro extends API {

    public function __construct() {
        parent::__construct();
        $this->setToken();
    }

    protected function setToken(): string {
        if (isset($this->token) && isset($this->tokenExpiry)):
            $in15minutes = Carbon::now(config('app.timezone'))->addMinutes(15);
            if ($this->tokenExpiry instanceof Carbon  && $this->tokenExpiry->isBefore($in15minutes)):
                return $this->token;
            else:
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
        return $this->token;
    }

    /**
     * Henter alle maskiner fra Jamf Pro
     */
    public function getJamfComputers() {
        $uri = '/api/v1/computers-inventory';
        $results = [];
        $gotAll = false;
        $page=0;
        $page_size=100;
        $params = [
            'section' => 'GENERAL,HARDWARE,USER_AND_LOCATION,OPERATING_SYSTEM',
            'page-size' => $page_size,
            'page' => $page
        ];
        while (!$gotAll):
            $params['page'] = $page;
            $response = $this->apiQuery($uri, $params, true);
            $results = array_merge($results, $response->json('results'));
            $gotAll = $response->json('totalCount') <= $page_size * ($page + 1);
            $page++;
        endwhile;

        return $results;
    }

    public function getJamfMobileDevices($detailed = true) {
        $page=0;
        $page_size=100;
        $gotAll = false;
        $results = [];
        while (!$gotAll):
            $uri = '/api/v2/mobile-devices?page='.$page.'&page-size='.$page_size;
            $response = $this->api->request('GET', $uri, $this->options);
            $data = json_decode($response->getBody()->getContents(), true);
            $results = array_merge($results, $data['results']);
            $gotAll = $data['totalCount'] <= $page_size * ($page + 1);
            $page++;
        endwhile;
        if ($detailed):
            $detailedResults = [];
            foreach ($results as $dev):
                $uri = '/api/v2/mobile-devices/'.$dev['id'].'/detail';
                $response = $this->api->request('GET', $uri, $this->options);
                $data = json_decode($response->getBody()->getContents(), true);
                $detailedResults[] = $data;
            endforeach;

            return $detailedResults;
        else:
            return $results;
        endif;
    }
}
