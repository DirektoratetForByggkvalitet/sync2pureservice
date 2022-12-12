<?php
// Config for SvarInnController
return [
    'dryrun' => env('SVARINN_DRYRUN', false),
    'base_uri' => 'https://svarut.ks.no',
    'urlHentForsendelser' => '/tjenester/svarinn/mottaker/hentNyeForsendelser',
    'urlSettMottatt' => '/tjenester/svarinn/kvitterMottatt/forsendelse',
    'urlMottakFeilet' => '/tjenester/svarinn/mottakFeilet/forsendelse',
    'username' => env('SVARINN_USER', null),
    'secret' => env('SVARINN_SECRET', null),
    'privatekey_path' => env('SVARINN_PRIVATEKEY_PATH', base_path('keys/privatekey.pem')),
    'max_retries' => env('SVARINN_MAX_RETRIES', 3),
    'temp_path' => env('SVARINN_TEMP_PATH', storage_path('svarinn_tmp')),
    'download_path' => env('SVARINN_DOWNLOAD_PATH', storage_path('svarinn_download')),
    'dekrypt_path' => env('SVARINN_DEKRYPT_PATH', storage_path('svarinn_dekryptert')),
    'dekrypter' => [
        'version' => env('DEKRYPTERVER', '1.0'),
        'jar' => env('DEKRYPTER_JAR', null),
    ],
    'pureservice' => [
        'source' => env('SVARINN_PS_SOURCE', 'SvarUt'),
        'zone' => env('SVARINN_PS_ZONE', 'Dispatchers'),
        'team' => env('SVARINN_PS_TEAM', 'Dispatcher'),
        'role_id' => env('SVARINN_PS_USER_ROLE_ID', 10),
        'visibility' => env('SVARINN_PS_VISIBILITY', 2),
        'ticketType' => env('SVARINN_PS_TICKET_TYPE', 'Henvendelse'),
        'priority' => env('SVARINN_PS_PRIORITY', 'Normal'),
        'status' => env('SVARINN_PS_STATUS', 'Ny'),
        'requestType' => env('SVARINN_PS_REQUEST_TYPE','Ticket'),
    ],
];
