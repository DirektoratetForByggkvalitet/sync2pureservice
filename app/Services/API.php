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
    protected false|string $auth = false;

    public function __construct() {
        $this->setProperties();
    }

    /**
     * Åpner for å endre url og auth ved å oppgi en konfig-prefiks
     * Standard prefiks er 'api' (som gir f.eks. 'eformidling.api.url')
     * For å bruke digdir sitt test-integrasjonspunkt kan vi bruke prefix 'testapi',
     * som de vil bruke 'eformidling.testapi' for å finne innstillingene
     */
    public function setProperties(string $prefix = 'api') {
        $this->cKey =Str::lower(class_basename($this));
        $this->auth = $this->myConf($prefix.'.auth', false);
        $this->prefix = $this->myConf($prefix.'.prefix', '');
        $this->base_url = $this->myConf($prefix.'.url');
        // Beholder samme User-Agent uansett prefix
        if (env('BITBUCKET_COMMIT', false)):
            $userAgent = class_basename($this).'-'.Str::limit(env('BITBUCKET_COMMIT'), 8).'/'.config('api.user-agent');
        else:
            $userAgent = class_basename($this).'/'.config('api.user-agent');
        endif;
        config([
            Str::lower(class_basename($this)).'.api.user-agent' => $userAgent,
        ]);
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
        $request = Http::withUserAgent($this->myConf('api.user-agent', config('api.user-agent')));
        // Setter timeout for forespørselen
        $request->timeout($this->myConf('api.timeout', config('api.timeout')));
        $request->retry($this->myConf('api.retry', config('api.retry')));
        // Setter headers
        $request->withHeaders([
            'Connection' => $this->myConf('api.headers.connection', config('api.headers.connection')),
            'Accept-Encoding' => $this->myConf('api.headers.accept-encoding', config('api.headers.accept-encoding')),
        ]);
        // Korrigerer manglende prefix
        if ($this->myConf('api.prefix', false) && $this->prefix == ''):
            $this->setPrefix($this->myConf('api.prefix'));
        endif;
        if ($this->myConf('api.url', false)):
            $request->baseUrl($this->myConf('api.url').$this->prefix);
        endif;
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
        if ($contentType && !in_array($contentType, ['none', 'no', 'auto'])):
            $request->contentType($contentType);
        endif;

        if ($options):
            $request->withOptions($options);
        endif;

        return $request;
    }

    public function resolveUri(string $path): string {
        return Str::replace('//', '/', '/' . $path);
        // if (Str::startsWith($path, 'https:') || Str::startsWith($path, 'http:')):
        //     return $path; // Returnerer samme verdi, siden det er en full URL
        // else:
        //     return Str::replace('//', '/', '/' . $path);
        // endif;
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
    public function apiPost
        (
            string $uri,
            mixed $body = null,
            string|null $accept = null,
            string|null $contentType = null,
            bool $returnBool = false,
            null|array $withOptions = null
        ): Response|bool
    {
        $uri = $this->resolveUri($uri);
        $response = $this->prepRequest($accept, $contentType, $withOptions)->post($uri, $body);
        if ($returnBool) return $response->successful();
        return $response;
    }

    /**
     * PATCH-forespørsel mot APIet
     */
    public function apiPatch
        (
            string $uri,
            array $body,
            string|null $contentType = null,
            bool $returnBool = false,
            null|array $withOptions = null
        ): Response|bool
    {
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
