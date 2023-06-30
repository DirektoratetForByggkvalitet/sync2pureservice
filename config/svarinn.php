<?php
// Config for SvarInnController
return [
    'dryrun' => env('SVARINN_DRYRUN', false),
    'urlHentForsendelser' => '/tjenester/svarinn/mottaker/hentNyeForsendelser',
    'urlSettMottatt' => '/tjenester/svarinn/kvitterMottak/forsendelse',
    'urlMottakFeilet' => '/tjenester/svarinn/mottakFeilet/forsendelse',
    'api' => [
        'url' => 'https://svarut.ks.no',
        'auth' => 'basic',
        'user' => env('SVARINN_USER', null),
        'password' => env('SVARINN_SECRET', null),
    ],
    'privatekey_path' => env('SVARINN_PRIVATEKEY_PATH', storage_path('privatekey.pem')),
    'max_retries' => env('SVARINN_MAX_RETRIES', 3),
    'temp_path' => env('SVARINN_TEMP_PATH', 'svarinn_tmp'),
    'download_path' => env('SVARINN_DOWNLOAD_PATH', 'svarinn_download'),
    'dekrypt_path' => env('SVARINN_DEKRYPT_PATH', 'svarinn_dekryptert'),
    'dekrypter' => [
        'version' => env('DEKRYPTERVER', '1.0'),
        'jar' => env('DEKRYPTER_JAR', null),
    ],
    'pureservice' => [
        'source' => env('SVARINN_PS_SOURCE', 'SvarUt'),
        'zone' => env('SVARINN_PS_ZONE', 'Dispatchers'),
        'team' => env('SVARINN_PS_TEAM', 'Dispatcher'),
        'visibility' => env('SVARINN_PS_VISIBILITY', 2),
        'ticketType' => env('SVARINN_PS_TICKET_TYPE', 'Henvendelse'),
        'priority' => env('SVARINN_PS_PRIORITY', 'Normal'),
        'status' => env('SVARINN_PS_STATUS', 'Ny'),
        'requestType' => env('SVARINN_PS_REQUEST_TYPE','Ticket'),
    ],
];
