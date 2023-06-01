<?php

namespace App\Services;
use Psr\Http\Message\{RequestInterface, ResponseInterface};
use GuzzleHttp\{Client, HandlerStack, Middleware, RetryMiddleware, RequestOptions};
use Illuminate\Support\{Str, Arr};

/**
 * Generell klasse for å kommunisere med ulike RESTful APIer.
 */
class API {
    protected $cKey;
    public $base_url;
    public $worker;

    protected $prefix = ''; // Prefiks til uri
    protected $options = [
        'headers' => [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json; charset=utf-8',
            'Connection' => 'keep-alive',
            'Accept-Encoding' => 'gzip, deflate, br',
            'User-Agent' => 'sync2pureservice/PHP'
        ],
    ];

    public function __construct() {
        $this->cKey = Str::lower(class_basename($this));
        $this->getClient();
    }

    public function getCKey(): string {
        return $this->cKey;
    }

    protected function myConf($key): mixed {
        return config($this->cKey.'.'.$key);
    }

    /**
     * Oppretter en GuzzleHttp-klient til bruk mot Pureservice
    */
    private function getClient(): void {
        $maxRetries = config($this->cKey.'.maxretries', 3);

        // Funksjon som finner ut om vi skal kjøre en retry
        $decider = function(int $retries, RequestInterface $request, ResponseInterface $response = null) use ($maxRetries) : bool {
            return
                $retries < $maxRetries
                && null !== $response
                && 429 === $response->getStatusCode();
        };

        // Funksjon for å finne ut hvor lenge man skal vente
        $delay = function(int $retries, ResponseInterface $response) : int {
            if (!$response->hasHeader('Retry-After')) {
                return RetryMiddleware::exponentialDelay($retries);
            }

            $retryAfter = $response->getHeaderLine('Retry-After');

            if (!is_numeric($retryAfter)) {
                $retryAfter = (new \DateTime($retryAfter))->getTimestamp() - time();
            }

            return (int) $retryAfter * 1000;
        };

        $stack = HandlerStack::create();
        $stack->push(Middleware::retry($decider, $delay));

        $this->base_url = config($this->cKey.'.api_url');
        $this->worker = new Client([
            'base_uri' => config($this->cKey.'.api_url'),
            'timeout'         => 30,
            'allow_redirects' => false,
            'handler' => $stack
        ]);
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
     * Brukes til å kjøre en GET-forespørsel mot APIet
     * @param   string  $uri                Relativ URI for forespørselen
     * @param   bool    $returnResponse     Angir om returverdien skal være et responsobjet eller et array
     *
     * @return  Psr\Http\Message\ResponseInterface/assoc_array  Resultat som array eller objekt
    */
    public function apiGet($uri, $returnResponse = false, $acceptHeader = false): array|ResponseInterface {
        $uri = $this->prefix.$uri;
        $options = $this->options;
        if ($acceptHeader)
            $options['headers']['Accept'] = $acceptHeader;
        $response = $this->worker->get($uri, $options);
        if ($returnResponse) return $response;
        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * Brukes til å kjøre en POST-forespørsel mot Pureservice
     * @param   string      $uri    Relativ URI for forespørselen
     * @param   assoc_array $body   JSON-innholdet til forespørselen, som assoc_array
     * @param   string      $ct     Content-Type for forespørselen, med standardverdi
     *
     * @return  Psr\Http\Message\ResponseInterface  Resultatobjekt for forespørselen
     */
    public function apiPOST($uri, $body, $ct='application/vnd.api+json; charset=utf-8'): ResponseInterface {
        $uri = $this->prefix.$uri;
        $options = $this->options;
        $options['json'] = $body;
        if ($ct) $options['headers']['Content-Type'] = $ct;
        return $this->worker->post($uri, $options);
    }

     /**
     * Brukes til å kjøre en PATCH-forespørsel mot Pureservice
     * @param   string      $uri    Relativ URI for forespørselen
     * @param   assoc_array $body   JSON-innholdet til forespørselen, som assoc_array
     *
     * @return  Psr\Http\Message\ResponseInterface  Resultatobjekt for forespørselen
     */
    public function apiPATCH($uri, $body, $returnBool = false): ResponseInterface|bool {
        $uri = $this->prefix.$uri;
        $options = $this->options;
        $options['json'] = $body;
        $result = $this->worker->patch($uri, $options);
        if ($returnBool):
            if ($result->getStatusCode() < 210):
                return true;
            else:
                return false;
            endif;
        else:
            return $result;
        endif;
    }

     /**
     * Brukes til å kjøre en PUT-forespørsel (oppdatering) mot Pureservice
     * @param   string      $uri    Relativ URI for forespørselen
     * @param   assoc_array $body   JSON-innholdet til forespørselen, som assoc_array
     *
     * @return  Psr\Http\Message\ResponseInterface|bool  Resultatobjekt for forespørselen
     */
    public function apiPut($uri, $body, $returnBool = false) : ResponseInterface|bool {
        $uri = $this->prefix.$uri;
        $options = $this->options;
        $options['json'] = $body;
        $result = $this->worker->put($uri, $options);
        if ($returnBool):
            if ($result->getStatusCode() < 210):
                return true;
            else:
                return false;
            endif;
        else:
            return $result;
        endif;
    }

    /**
     * Brukes til å kjøre en DELETE-forespørsel mot Pureservice
     * @param   string      $uri    Relativ URI for forespørselen
     *
     * @return  bool                true hvis slettingen ble gjennomført, false hvis ikke
     */
    public function apiDelete($uri) {
        $uri = $this->prefix.$uri;
        $response = $this->worker->delete($uri, $this->options);
        if ((int) $response->getStatusCode() >= 300):
            return false;
        endif;
        return true;
    }


}
