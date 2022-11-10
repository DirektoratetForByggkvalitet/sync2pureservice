<?php
// Config for SvarInnController
return [
    'base_uri' => 'https://svarut.ks.no',
    'urlHentForsendelser' => '/tjenester/svarinn/mottaker/hentNyeForsendelser',
    'urlSettMottatt' => '/tjenester/svarinn/mottaker/settForsendelseMottatt',
    'username' => env('SVARINN_USER', null),
    'secret' => env('SVARINN_SECRET', null),
    'private_key' => env('SVARINN_PRIVATEKEY'),
    'max_retries' => env('SVARINN_MAX_RETRIES', 3),
];
