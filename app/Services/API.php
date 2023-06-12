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
        $this->cKey = Str::lower(class_basename($this));
        $this->setProperties();
        //$this->getClient();
    }

    protected function setProperties() {
        $this->auth = $this->myConf('api.auth', false);
        $this->prefix = $this->myConf('api.prefix', '');
        $this->base_url = $this->myConf('api.url');
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
    public function prepRequest(string|null $accept = null, string|null $contentType = null): PendingRequest {
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
            $contentType ? $request->accept($accept) : $request->accept($this->myConf('api.accept'));
        else:
            $request->acceptJson();
        endif;
        if ($contentType):
            $request->contentType($contentType);
        endif;
        return $request;
    }

    public function resolveUri(string $path): string {
        if (Str::startsWith($path, 'https://') || Str::startsWith($path, 'http://')):
            return $path; // Returnerer samme verdi, siden det er en full URL
        else:
            return $this->myConf('api.url') . Str::replace('//', '/', '/' . $this->prefix . '/' . $path);
        endif;
    }

    /**
     * GET-forespørsel mot APIet
     * @param   string  $uri            Full URL til forespørselen
     * @param   bool    $returnResponse Returnerer Response-objektet, fremfor kun dataene
     * @param   string  $contentType    Setter Content-Type for forespørselen
     */
    public function apiGet(string $uri, bool $returnResponse = false, string|null|false $accept = null, array $query = [], bool $statusOnError = false): Response|array|false {
        $uri = $this->resolveUri($uri);
        $response = $this->prepRequest($accept)->get($uri, $query);
        if ($response->successful()):
            if ($returnResponse) return $response;
            return $response->json();
        endif;
        if ($statusOnError) return $response->status();
        return false;
    }

    public function ApiQuery(string $uri,
        array $query = [],
        bool $returnResponse = false,
        string|null|false $accept = null,
        bool $statusOnError = false
    ): Response|array|false {
        return $this->apiGet($uri, $returnResponse, $accept, $query, $statusOnError);
    }

    /**
     * POST-forespørsel mot APIet
     */
    public function apiPost(string $uri, array $body, string|null $accept = null, string|null $contentType = null, bool $returnBool = false): Response {
        $uri = $this->resolveUri($uri);
        $response = $this->prepRequest($accept, $contentType)->post($uri, $body);
        if ($returnBool) return $response->successful();
        return $response;
    }

    /**
     * PATCH-forespørsel mot APIet
     */
    public function apiPatch(string $uri, array $body, string|null $contentType = null, bool $returnBool = false): Response|bool {
        $uri = $this->resolveUri($uri);
        $response = $this->prepRequest($contentType)->patch($uri, $body);
        if ($returnBool) return $response->successful();
        return $response;
    }

    /**
     * PUT-forespørsel mot APIet
     */
    public function apiPut(string $uri, array $body, string|null $contentType = null, bool $returnBool = false) : Response|bool {
        $uri = $this->resolveUri($uri);
        $response = $this->prepRequest($contentType)->put($uri, $body);
        if ($returnBool) return $response->successful();
        return $response;
   }

    /**
     * DELETE-forespørsel mot APIet
     */
    public function apiDelete($uri, array $body = [], string|null $contentType = null): bool {
        $uri = $this->resolveUri($uri);
        $response = $this->prepRequest($contentType)->delete($uri, $body);
        return $response->successful();
    }

    protected function human_filesize($bytes, $decimals = 2): string {
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
