<?php

namespace App\Http\Controllers;
use Psr\Http\Message\{RequestInterface, ResponseInterface};
use GuzzleHttp\{Client, HandlerStack, Middleware, RetryMiddleware};

class BrregController extends Controller {
    protected $api;
    protected $options;
    //

    public function __construct() {
        $this->getClient();
        $this->setOptions();
    }

    /**
     * Oppretter en Guzzle HTTP-klient mot BRREG sitt API
     */
    protected function getClient() {
        $maxRetries = config('brreg.maxretries');

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
            'base_uri' => config('brreg.base_uri'),
            'timeout'         => 30,
            'allow_redirects' => false,
            'handler' => $stack
        ]);

    }

    /**
     * Setter innstillinger for å kontakte APIet
     */
    protected function setOptions() {
        $this->options = [
            'headers' => [
                'Accept' => 'application/json',
                'Connection' => 'keep-alive',
                'Accept-Encoding' => 'gzip, deflate, br',
            ],
        ];
    }
}
