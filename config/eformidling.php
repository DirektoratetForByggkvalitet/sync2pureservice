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
    'download_path' => env('EF_DOWNLOAD_PATH', storage_path('app/dpe_download')),
    'out' => [
        'process' => env('EF_MESSAGE_PROCESS', 'urn:no:difi:profile:arkivmelding:administrasjon:ver1.0'),
        'template' => env('EF_MESSAGE_TEMPLATE', storage_path('arkivmelding.json')),
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
