<?php

namespace App\Http\Controllers;

use Psr\Http\Message\{RequestInterface, ResponseInterface};
use GuzzleHttp\{Client, HandlerStack, Middleware, RetryMiddleware, RequestOptions};

class SvarInnController extends Controller {
    protected $api;
    protected $options;


    public function __construct() {
        $this->getClient();
        $this->setOptions();
    }

    /**
     * Oppretter API-kobling til SvarInn
     */
    private function getClient() {
        $maxRetries = config('svarinn.maxretries');

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
                return RetryMiddleware::exponentialDelay($this->retries);
            }

            $retryAfter = $response->getHeaderLine('Retry-After');

            if (!is_numeric($retryAfter)) {
                $retryAfter = (new \DateTime($retryAfter))->getTimestamp() - time();
            }

            return (int) $retryAfter * 1000;
        };

        $stack = HandlerStack::create();
        $stack->push(Middleware::retry($decider, $delay));

        $this->api = new Client([
            'base_uri' => config('svarinn.base_uri'),
            'timeout'         => 30,
            'allow_redirects' => false,
            'handler' => $stack
        ]);
    }

    /**
     * Setter innstillinger for koblingen mot APIet, inkludert innlogging
     */
    private function setOptions() {
        $this->options = [
            'headers' => [
                'Accept' => 'application/json',
                'Connection' => 'keep-alive',
                'Accept-Encoding' => 'gzip, deflate, br',
            ],
            'auth' => [
                config('svarinn.username'),
                config('svarinn.secret')
            ]
        ];
        //$this->options['http_errors'] = false;
        return false;
    }

    /**
     * Se etter meldinger, returner array med meldinger, evt tomt array
     */
    public function sjekkForMeldinger($returnResponse=false) {
        $uri = config('svarinn.urlHentForsendelser');
        $options = $this->options;
        $response = $this->api->get($uri, $this->options);
        if ($returnResponse) return $response;
        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * Setter en forsendelse som mottatt av mottakssystemet
     */
    public function settForsendelseMottatt($forsendelseId) {
        $uri = config('svarinn.urlSettMottatt').'/'.$forsendelseId;
        $options = $this->options;
        return $this->api->post($uri, $options);
    }

}
