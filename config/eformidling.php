<?php

return [
    'api' => [
        'url' => env('EF_IP_URL', ''),
        'prefix' => env('EF_IP_PREFIX', 'api'),
        'auth' => env('EF_IP_AUTH', false),
        'user' => env('EF_IP_USER'),
        'password' => env('EF_IP_PASS'),
        'accept' => null,
        'asic_accept' => 'application/vnd.etsi.asic-e+zip',
    ],
    'address' => [
        'prefix' => '0192:',
        'sender_id' => env('EF_SENDER_ID'),
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
    'ticket' => [
        'codeTemplate' => env('PURESERVICE_TICKET_NUMBER_TEMPLATE', '[Sak ID# {{RequestNumber}}]'),
        'source' => env('EF_TICKET_SOURCE', 'eForsendelse'),
        'zone' => env('EF_TICKET_ZONE', 'Fordeling'),
        'team' => env('EF_TICKET_TEAM', 'Postmottak'),
        'visibility' => env('EF_TICKET_VISIBILITY', 2),
        'ticketType' => env('EF_TICKET_TYPE', 'Henvendelse'),
        'priority' => env('EF_TICKET_PRIORITY', 'Normal'),
        'status' => env('EF_TICKET_STATUS', 'Ny'),
        'status_solved' => env('PURESERVICE_TICKET_SOLVED_STATUS', 'Løst'),
        'requestType' => env('PURESERVICE_TICKET_REQUEST_TYPE','Ticket'),
    ],

];
