<?php

namespace App\Services;
use Illuminate\Http\Client\{Response, PendingRequest};
use Illuminate\Support\{Str, Arr};
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;


/**
 * Generell klasse for å kommunisere med ulike RESTful APIer.
 */
class API {
    protected $cKey;
    public $base_url;

    protected $prefix = ''; // Prefiks til uri
    protected $options = [
        'headers' => [
            'Content-Type' => 'application/json; charset=utf-8',
            'Connection' => 'keep-alive',
            'Accept-Encoding' => 'gzip, deflate, br',
            'User-Agent' => 'sync2pureservice/PHP'
        ],
    ];
    protected false|string $auth = false;

    public function __construct() {
        $this->setCKey(Str::lower(class_basename($this)));
    }

    /**
     * Åpner for å endre url og auth ved å oppgi en konfig-prefiks
     * Standard prefiks er 'api' (som gir f.eks. 'eformidling.api.url')
     * For å bruke digdir sitt test-integrasjonspunkt kan vi bruke prefix 'testapi',
     * som de vil bruke 'eformidling.testapi' for å finne innstillingene
     */
    public function setProperties(string $prefix = 'api') {
        $this->auth = $this->myConf($prefix.'.auth', false);
        $this->prefix = $this->myConf($prefix.'.prefix', '');
        $this->base_url = $this->myConf($prefix.'.url');
    }

    public function setBaseUrl(string $url): void {
        $this->base_url = $url;
    }

    public function getBaseUrl(): string {
        return $this->base_url;
    }
    /**
     * Set the value of cKey
     */
    public function setCKey($cKey) {
        $this->cKey = $cKey;
        // Endrer properties når cKey endres
        $this->setProperties();
    }

    public function getCKey(): string {
        return $this->cKey;
    }

    public function myConf($key, $default = null): mixed {
        return config($this->cKey.'.'.$key, $default);
    }

    /**
     * Setter standardvalg for GuzzleHttp-klienten
     */
    public function setOptions(array $options): void {
        $this->options = $options;
        //$this->options['http_errors'] = false;
    }

    /**
     * Henter ut standardvalgene for GuzzleHttp-klienten
     */
    public function getOptions(): array {
        return $this->options;
    }

    /**
     * Setter prefiks for alle API-kall (det som kommer etter 'https://server.no' i alle kall)
     */
    public function setPrefix(string $prefix): void {
        $this->prefix = $prefix;
    }

    /**
     * Preparerer en PendingRequest med muligheter for autorisering
     * @param   string    $contentType    Setter forespørselens Content-Type, standard 'application/json'
     * @return  Illuminate\Http\Client\PendingRequest
     */
    public function prepRequest(string|null $accept = null, string|null $contentType = null, null|array $options = null): PendingRequest {
        $headers = $this->options['headers'];
        $request = Http::withHeaders($headers);
        if ($this->auth):
            switch ($this->auth):
                case 'digest':
                    $request->withDigestAuth($this->myConf('api.user'), $this->myConf('api.password'));
                    break;
                case 'token':
                    $request->withToken($this->myConf('api.token'));
                    break;
                default: // basic auth
                    $request->withBasicAuth($this->myConf('api.user'), $this->myConf('api.password'));
            endswitch;
        endif;
        if ($accept || $this->myConf('api.accept', false)):
            $accept ? $request->accept($accept) : $request->accept($this->myConf('api.accept'));
        else:
            $request->acceptJson();
        endif;
        if ($contentType):
            $request->contentType($contentType);
        endif;
        // Setter timeout for forespørselen
        $request->timeout($this->myConf('api.timeout', 90));

        if ($options):
            $request->withOptions($options);
        endif;

        return $request;
    }

    public function resolveUri(string $path): string {
        if (Str::startsWith($path, 'https:') || Str::startsWith($path, 'http:')):
            return $path; // Returnerer samme verdi, siden det er en full URL
        else:
            return $this->base_url . Str::replace('//', '/', '/' . $this->prefix . '/' . $path);
        endif;
    }

    /**
     * GET-forespørsel mot APIet
     * @param   string  $uri            Full URL til forespørselen
     * @param   bool    $returnResponse Returnerer Response-objektet, fremfor kun dataene
     * @param   string  $contentType    Setter Content-Type for forespørselen
     */
    public function apiGet(string $uri, bool $returnResponse = false, string|null|false $accept = null, null|array $query = null, null|array $withOptions = null): mixed {
        $uri = $this->resolveUri($uri);
        $query = is_array($query) ? $query : [];
        $response = $this->prepRequest($accept, null, $withOptions)->get($uri, $query);
        if ($response->successful()):
            if ($returnResponse) return $response;
            return $response->json();
        endif;
        return $returnResponse ? $response : false;
    }

    /**
     * Samme som apiGet, men forenklet for å ta imot URL-parametre
     */
    public function apiQuery(
        string $uri,
        array $query = [],
        bool $returnResponse = false,
        string|null|false $accept = null,
        null|array $withOptions = null
    ): Response|array|false {
        return $this->apiGet($uri, $returnResponse, $accept, $query, $withOptions);
    }

    /**
     * POST-forespørsel mot APIet
     */
    public function apiPost(string $uri, mixed $body = null, string|null $accept = null, string|null $contentType = null, bool $returnBool = false, null|array $withOptions = null): Response|bool {
        $uri = $this->resolveUri($uri);
        $response = $this->prepRequest($accept, $contentType, $withOptions)->post($uri, $body);
        if ($returnBool) return $response->successful();
        return $response;
    }

    /**
     * PATCH-forespørsel mot APIet
     */
    public function apiPatch(string $uri, array $body, string|null $contentType = null, bool $returnBool = false, null|array $withOptions): Response|bool {
        $uri = $this->resolveUri($uri);
        $accept = $this->myConf('api.accept', 'application/json');
        $contentType = $contentType ? $contentType : $this->myConf('api.contentType', $accept);
        $response = $this->prepRequest($accept, $contentType, $withOptions)->patch($uri, $body);
        return $returnBool ? $response->successful() : $response;
    }

    /**
     * PUT-forespørsel mot APIet
     */
    public function apiPut(string $uri, array $body, string|null $contentType = null, bool $returnBool = false) : Response|bool {
        $uri = $this->resolveUri($uri);
        $accept = $this->myConf('api.accept');
        $contentType = $contentType ? $contentType : $accept;
        $response = $this->prepRequest($accept, $contentType)->put($uri, $body);
        if ($returnBool) return $response->successful();
        return $response;
   }

    /**
     * DELETE-forespørsel mot APIet
     */
    public function apiDelete($uri, array $body = [], string|null $contentType = null): bool {
        $uri = $this->resolveUri($uri);
        $accept = $this->myConf('api.accept');
        $contentType = $contentType ? $contentType : $accept;
        $response = $this->prepRequest($accept)->delete($uri, $body);
        return $response->successful();
    }

    public function human_filesize($bytes, $decimals = 2): string {
        $sz = 'BKMGTP';
        $factor = floor((strlen($bytes) - 1) / 3);
        return sprintf("%.{$decimals}f", $bytes / pow(1024, $factor)) . @$sz[$factor];
    }

    protected function dateFromEpochTime($ts): string {
        return Carbon::createFromTimestampMs($ts, config('app.timezone'))
            ->locale(config('app.locale'))
            ->toDateTimeString();
    }


}
