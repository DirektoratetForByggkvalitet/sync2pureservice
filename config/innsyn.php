<?php

return [
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
    'parked_status' => env('DPE_PARKED_STATUS', 'Venter - levering'),
];
