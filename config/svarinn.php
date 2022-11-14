<?php
// Config for SvarInnController
return [
    'base_uri' => 'https://svarut.ks.no',
    'urlHentForsendelser' => '/tjenester/svarinn/mottaker/hentNyeForsendelser',
    'urlSettMottatt' => '/tjenester/svarinn/mottaker/settForsendelseMottatt',
    'username' => env('SVARINN_USER', null),
    'secret' => env('SVARINN_SECRET', null),
    'privatekey_path' => env('SVARINN_PRIVATEKEY_PATH', null),
    'max_retries' => env('SVARINN_MAX_RETRIES', 3),
    'temp_path' => env('SVARINN_TEMP_PATH', null),
    'download_path' => env('SVARINN_DOWNLOAD_PATH', null),
    'dekrypt_path' => env('SVARINN_DEKRYPT_PATH', null),
    'temp_path' => env('SVARINN_TEMP_PATH', null),
    'dekrypter' => [
        'version' => env('DEKRYPTERVER', '1.0'),
        'jar' => env('DEKRYPTER_JAR', null),
    ],
];
