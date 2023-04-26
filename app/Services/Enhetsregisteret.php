<?php

namespace App\Services;
use App\Services\API;
use Psr\Http\Message\{RequestInterface, ResponseInterface};
use GuzzleHttp\{Client, HandlerStack, Middleware, RetryMiddleware, RequestOptions};

class Enhetsregisteret extends API {

    public function __construct() {
        parent::__construct();
        $this->prefix = config($this->cKey.'.prefix');
    }
}
