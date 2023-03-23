<?php

return [
    // search angir hvordan vi skal finne innsynskrav som er klare for behandling
    'search' => [
        'ticketType' => env('DPE_SEARCH_TICKET_TYPE', env('DPE_TICKET_TYPE', 'Innsynskrav')),
        'team' => env('DPE_SEARCH_TEAM', 'Automatikk'),
        'status' => env('DPE_SEARCH_STATUS', 'Ny'),
    ],
    'ticketType' => env('DPE_TICKET_TYPE', 'Innsynskrav'),
    'source' => env('DPE_SOURCE', 'E-post'),
    'zone' => env('DPE_ZONE', 'Dispatchers'),
    'team' => env('DPE_TEAM', 'Dispatcher'),
    'visibility' => env('DPE_VISIBILITY', 2),
    'ticketType' => env('DPE_TICKET_TYPE', 'Henvendelse'),
    'priority' => env('DPE_PRIORITY', 'Normal'),
    'status' => env('DPE_STATUS', 'Ny'),
    'requestType' => env('PS_REQUEST_TYPE','Ticket'),
    'ip' => [
        'url' => env('DPE_IP_URL', null),
        'auth' => env('DPE_IP_AUTH', false),
        'user' => env('DPE_IP_USER', null),
        'password' => env('DPE_IP_PASSWORD', null),
    ],
    'ticketType_finished' => env('DPE_TICKET_TYPE_FINISHED', 'X-sak'),
];
