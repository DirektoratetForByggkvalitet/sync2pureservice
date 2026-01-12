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
    protected string $cKey;
    public string $base_url;
    public null|array|string $error_json = null; // Mottatt feilmelding fra JSON-format
    private string $version = '1.5';
    protected string|false $token = false;
    protected Carbon|false $tokenExpiry = false;

    protected $prefix = ''; // Prefiks til uri
    public false|string $auth = false;

    public function __construct() {
        $this->setCKey(Str::lower(class_basename($this)));
        $this->setProperties();
    }

    /**
     * Åpner for å endre url og auth ved å oppgi en konfig-prefiks
     * Standard prefiks er 'api' (som gir f.eks. 'eformidling.api.url')
     * For å bruke digdir sitt test-integrasjonspunkt kan vi bruke prefix 'testapi',
     * som de vil bruke 'eformidling.testapi' for å finne innstillingene
     */
    public function setProperties(string $prefix = 'api') {
        $this->auth = $this->myConf($prefix.'.auth', false);

        $this->setPrefix($this->myConf($prefix.'.prefix', false));

        $this->base_url = $this->myConf($prefix.'.url').$this->prefix;

        // Beholder samme User-Agent uansett prefix
        $userAgent = class_basename($this).'/'.config('api.user-agent');

        config([
            Str::lower(class_basename($this)).'.api.user-agent' => $userAgent,
        ]);
        if ($this->auth == 'token'):
            $this->setToken();
        endif;

    }

    /**
     * Funksjon for å regne ut eller hente inn Token for innlogging. Kan overstyres i underklasser
     */
    protected function setToken(): void {
        $this->token = $this->myConf('api.token', false);
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
     * Henter token for innlogging
     */
    protected function getToken(): string|false {
        return $this->token;
    }

    /**
     * Setter prefiks for alle API-kall (det som kommer etter 'https://server.no' i alle kall)
     * Korrigerer prefix som ikke starter med '/'
     */
    public function setPrefix(string|false $myPrefix): void {
        $this->prefix = $myPrefix ? Str::replace('//', '/', '/'.$myPrefix): '';
    }

    /**
     * Preparerer en PendingRequest med muligheter for autorisering
     * @param   string    $contentType    Setter forespørselens Content-Type, standard 'application/json'
     * @return  Illuminate\Http\Client\PendingRequest
     */
    public function prepRequest(
        string|null $accept = null, 
        string|null $contentType = 'auto', 
        null|string $toFile = null
    ): PendingRequest {
        $this->error_json = null;
        // Fornyer token dersom den trenger fornyelse
        $this->setToken();
        $request = Http::withUserAgent($this->myConf('api.user-agent', config('api.user-agent')));
        // Setter timeout for forespørselen
        $request->timeout($this->myConf('api.timeout', config('api.timeout')));
        // Setter timeout for oppkoblingen
        $request->connectTimeout($this->myConf('api.connectTimeout', config('api.connectTimeout')));
        // $request->retry($this->myConf('api.retry', config('api.retry')), ($this->myConf('api.retryWait', config('api.retryWait', 300))));
        // Setter headers
        $request->withHeaders([
            'Connection' => $this->myConf('api.headers.connection', config('api.headers.connection')),
            'Accept-Encoding' => $this->myConf('api.headers.accept-encoding', config('api.headers.accept-encoding')),
        ]);
        // Setter baseUrl
        if ($this->myConf('api.url', false)):
            $request->baseUrl($this->base_url);
        endif;
        // Setter opp autentisering, hvis oppgitt i config
        if ($this->auth):
            switch ($this->auth):
                case 'digest':
                    $request->withDigestAuth($this->myConf('api.user'), $this->myConf('api.password'));
                    break;
                case 'token':
                    $request->withToken($this->getToken());
                    break;
                case 'basic':
                default: // basic auth
                    //$request->withHeader('Authorization', 'Basic '.base64_encode($this->myConf('api.user').':'.$this->myConf('api.password')));
                    $request->withBasicAuth($this->myConf('api.user'), $this->myConf('api.password'));
            endswitch;
        endif;
        // Setter accept-headeren
        if ($accept || $this->myConf('api.accept', false)):
            $accept ? $request->accept($accept) : $request->accept($this->myConf('api.accept'));
        else:
            $request->acceptJson();
        endif;
        // Setter content-type
        if ($contentType && !in_array($contentType, ['none', 'no', 'auto'])):
            $request->contentType($contentType);
        elseif ($contentType == 'auto' && $this->myConf('api.contentType', false)):
            $request->contentType($this->myConf('api.contentType'));
        endif;

        // Hvis filplassering er oppgitt, bruk sink
        if ($toFile):
            $request->sink($toFile);
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
    public function apiGet (
        string $uri, 
        bool $returnResponse = false, 
        string|null|false $accept = null, 
        null|array $query = null, 
        null|string $toFile = null
    ): mixed {
        $uri = $this->resolveUri($uri);
        $query = is_array($query) ? $query : [];
        $retry = true;
        
        // Venter ved 429-status
        while ($retry):
            $response = $this->prepRequest($accept, null, $toFile)->get($uri, $query);
            if ($response->getStatusCode() == 429):
                $wait = $response->getHeader('Retry-After') && !is_array($response->getHeader('Retry-After')) ? $response->getHeader('Retry-After') : 10;
                sleep($wait);
            else:
                $retry = false;
            endif;
        endwhile;
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
        null|string $toFile = null
    ): mixed {
        return $this->apiGet($uri, $returnResponse, $accept, $query, $toFile);
    }

    /**
     * POST-forespørsel mot APIet
     */
    public function apiPost (
        string $uri,
        mixed $body = null,
        string|null $accept = null,
        string|null $contentType = 'auto',
        bool $returnBool = false,
        null|string $toFile = null
    ): Response|bool {
        $uri = $this->resolveUri($uri);
        $request = $this->prepRequest($accept, $contentType, $toFile);
        //$response = $request->post($uri, $body);
        $retry = true;
        while ($retry):
            $response = $request->post($uri, $body);
            if ($response->getStatusCode() == 429):
                $wait = $response->getHeader('Retry-After') && !is_array($response->getHeader('Retry-After')) ? $response->getHeader('Retry-After') : 8;
                sleep($wait);
            else:
                $retry = false;
            endif;
        endwhile;
        if ($returnBool) return $response->successful();
        return $response;
    }

    /**
     * PATCH-forespørsel mot APIet
     */
    public function apiPatch (
        string $uri,
        mixed $body,
        string|null $contentType = 'auto',
        mixed $returnOptions = false,
        null|string $toFile = null
    ): Response|bool {
        $uri = $this->resolveUri($uri);
        $accept = Str::contains($returnOptions, '/') ? $returnOptions : $this->myConf('api.accept', 'application/json');
        //$contentType = $contentType ? $contentType : $this->myConf('api.contentType', $accept);
        $retry = true;
        while ($retry):
            $response = $this->prepRequest($accept, $contentType, $toFile)->patch($uri, $body);
            if ($response->getStatusCode() == 429):
                $wait = $response->getHeader('Retry-After') ? $response->getHeader('Retry-After') : 10;
                sleep($wait);
            else:
                $retry = false;
            endif;
        endwhile;
        return $returnOptions === true ? $response->successful() : $response;
    }

    /**
     * PUT-forespørsel mot APIet
     */
    public function apiPut(
        string $uri,
        mixed $body,
        string|null $contentType = null,
        bool $returnBool = false
    ) : Response|bool {
        $uri = $this->resolveUri($uri);
        $accept = $this->myConf('api.accept');
        $contentType = $contentType ? $contentType : $accept;
        $retry = true;
        while ($retry):
            $response = $this->prepRequest($accept, $contentType)->put($uri, $body);
            if ($response->getStatusCode() == 429):
                $wait = $response->getHeader('Retry-After') ? $response->getHeader('Retry-After') : 10;
                sleep($wait);
            else:
                $retry = false;
            endif;
        endwhile;
                
        if ($returnBool) return $response->successful();
        return $response;
   }

    /**
     * DELETE-forespørsel mot APIet
     */
    public function apiDelete($uri, mixed $body = [], string|null $contentType = null): bool {
        $uri = $this->resolveUri($uri);
        $accept = $this->myConf('api.accept');
        $contentType = $contentType ? $contentType : $accept;
        $retry = true;
        while ($retry):
            $response = $this->prepRequest($accept)->delete($uri, $body);
            if ($response->getStatusCode() == 429):
                $wait = $response->getHeader('Retry-After') ? $response->getHeader('Retry-After') : 10;
                sleep($wait);
            else:
                $retry = false;
            endif;
        endwhile;
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
