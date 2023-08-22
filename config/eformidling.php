<?php
use Illuminate\Support\Str;
return [
    'api' => [
        'url' => env('EF_IP_URL', ''),
        'prefix' => env('EF_IP_PREFIX', 'api'),
        'auth' => env('EF_IP_AUTH', false),
        'user' => env('EF_IP_USER'),
        'password' => env('EF_IP_PASSWORD'),
        'accept' => null,
        'asic_accept' => 'application/vnd.etsi.asic-e+zip',
    ],
    'testapi' => [
        'url' => env('EF_TEST_IP_URL', 'https://qa-meldingsutveksling.difi.no/integrasjonspunkt/digdir-leikanger'),
        'auth' => env('EF_TEST_IP_AUTH', false),
        'user' => env('EF_TEST_IP_USER'),
        'password' => env('EF_TEST_IP_PASSWORD'),
        'prefix' => env('EF_TEST_IP_PREFIX', 'api'),
    ],
    'address' => [
        'prefix' => '0192:',
        'sender_id' => env('EF_SELF_ID', '000000000'),
        'sender_name' => env('EF_SELF_NAME', 'Avsendervirksomhet'),
        'digdir_ids' => [
            '987464291',
            '991825827'
        ]
    ],
    'system' => [
        'name' => env('EF_SYSTEM_NAME', 'sync2pureservice'),
        'systemId' => env('EF_SYSTEM_ID', Str::uuid()),
    ],
    'path' => [
        'download' => env('EF_DOWNLOAD_PATH', 'eformidling_download'),
        'temp' => env('EF_TEMP_PATH', 'eformidling_temp'),
    ],
    'out' => [
        'process' => env('EF_MESSAGE_PROCESS', 'planByggOgGeodata'),
        'type' => env('EF_MESSAGE_TYPE', 'arkivmelding'),
        'template' => env('EF_MESSAGE_VIEW', 'json.arkivmelding'),
    ],
    'process_pre' => 'urn:no:difi:profile:',
    'process_post' => ':ver1.0',
    'process' => [
        'administrasjon',
        'planByggOgGeodata',
        'helseSosialOgOmsorg',
        'oppvekstOgUtdanning',
        'kulturIdrettOgFritid',
        'trafikkReiserOgSamferdsel',
        'naturOgMiljoe',
        'naeringsutvikling',
        'skatterOgAvgifter',
        'tekniskeTjenester',
    ],
    'process_hr' => [
        'administrasjon' => 'administrasjon',
        'planByggOgGeodata' => 'plan, bygg og geodata',
        'helseSosialOgOmsorg' => 'helse, sosial og omsorg',
        'oppvekstOgUtdanning' => 'oppvekst og utdanning',
        'kulturIdrettOgFritid' => 'kultur, idrett og fritid',
        'trafikkReiserOgSamferdsel' => 'trafikk, reiser og samferdsel',
        'naturOgMiljoe' => 'natur og miljø',
        'naeringsutvikling' => 'næringsutvikling',
        'skatterOgAvgifter' => 'skatter og avgifter',
        'tekniskeTjenester' => 'tekniske tjenester',
    ],
];
