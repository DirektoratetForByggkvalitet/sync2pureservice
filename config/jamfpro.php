<?php

return [
    'api_url' => env('JAMFPRO_URL', ''),
    'api' => [
        'url' => env('JAMFPRO_URL', null),
        'username' => env('JAMFPRO_USER', ''),
        'password' => env('JAMFPRO_PASSWORD', ''),
        'prefix' => env('JAMFPRO_PREFIX', '/agent/api'),
        'token' => null,
        'auth' => 'tokenWithLogin',
        'accept' => 'application/vnd.api+json',
        'contentType' => 'application/vnd.api+json',
        'dlPath' => env('JAMFPRO_TMP_PATH', 'jamfpro'),
        'timeout' => 90,
    ],

];
