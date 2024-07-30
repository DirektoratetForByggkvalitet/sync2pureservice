<?php

/**
 * Innstillinger for App\Services\API
 */
return [
    'user-agent' => 'sync2pureservice 1.0 (PHP/GuzzleHttp)',
    'headers' => [
        'accept-encoding' => 'gzip, deflate, br',
        'connection' => 'keep-alive',
    ],
    'timeout' => 30,
    'retry' => 3,
];
