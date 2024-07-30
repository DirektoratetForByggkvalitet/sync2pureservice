<?php

return [
    'api' => [
        'url' => env('JAMFPRO_URL', null),
        'username' => env('JAMFPRO_USER', false),
        'password' => env('JAMFPRO_PASSWORD', null),
        'client_id' => env('JAMFPRO_CLIENTID', false),
        'client_secret' => env('JAMFPRO_SECRET', null),
        'prefix' => env('JAMFPRO_PREFIX', '/api'),
        'auth' => 'token',
        'dlPath' => env('JAMFPRO_TMP_PATH', 'jamfpro'),
        'timeout' => 90,
    ],

];
