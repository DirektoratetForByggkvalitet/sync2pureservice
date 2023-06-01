<?php

return [
    'api_url' => env('EF_IP_URL', ''),
    'api' => [
        'prefix' => env('EF_IP_PREFIX', 'api'),
        'auth' => env('EF_IP_AUTH', false),
        'username' => env('EF_IP_USER'),
        'password' => env('EF_IP_PASS'),
        'accept' => 'application/json',
        'asic_accept' => 'application/vnd.etsi.asic-e+zip',
    ],
    'download_path' => env('EF_DOWNLOAD_PATH', storage_path('dpe_download')),
    'out' => [
        'process' => env('EF_MESSAGE_PROCESS', 'urn:no:difi:profile:arkivmelding:administrasjon:ver1.0'),
        'template' => env('EF_MESSAGE_TEMPLATE', storage_path('arkivmelding.json')),
    ],
];
