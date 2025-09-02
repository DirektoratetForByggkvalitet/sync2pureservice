<?php

/**
 * Innstillinger for App\Services\API
 */
return [
    'user-agent' => 'sync2pureservice 1.0 (PHP/Laravel)',
    'headers' => [
        'accept-encoding' => 'gzip, deflate, br',
        'connection' => 'keep-alive',
    ],
    'timeout' => 30,
    'connectTimeout' => 10,
    'retry' => 3,
    'retryWait' => 300,
];
