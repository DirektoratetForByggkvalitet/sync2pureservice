<?php

return [
    'api_url' => env('PURESERVICE_URL', null),
    'apikey' =>  env('PURESERVICE_APIKEY', null),
    'computer' => [
        'displayName' => env('PURESERVICE_COMPUTER_ASSETTYPE_NAME', 'Datamaskin'),
        'lifespan' => env('PURESERVICE_COMPUTER_LIFESPAN', 4), // Forventet levetid, oppgitt som år
        'asset_type_id' => null, // Settes automatisk basert på displayName
        'relationship_type_id' => null, // Settes automatisk basert på displayName
        'className' => '', // Settes automatisk basert på displayName
        'fields' => [
            'name' => env('PURESERVICE_COMPUTER_FIELD_NAME', 'Navn'),
            'serial' => env('PURESERVICE_COMPUTER_FIELD_SERIAL', 'Serienr'),
            'model' => env('PURESERVICE_COMPUTER_FIELD_MODEL', 'Modell'),
            'modelId' => env('PURESERVICE_COMPUTER_FIELD_MODELID', 'ModelID'),
            'OsVersion' => env('PURESERVICE_COMPUTER_FIELD_OS', 'OS-versjon'),
            'processor' => env('PURESERVICE_COMPUTER_FIELD_PROCESSOR', 'Prosessor'),
            'jamfUrl' => env('PURESERVICE_COMPUTER_FIELD_JAMFURL', 'Jamf-URL'),
            'lastSeen' => env('PURESERVICE_COMPUTER_FIELD_LASTSEEN', 'Sist sett'),
            'memberSince' => env('PURESERVICE_COMPUTER_FIELD_MEMBERSINCE', 'Innmeldt'),
            'EOL' => env('PURESERVICE_COMPUTER_FIELD_EOL', 'EOL'),
        ],
        'properties' => [], // Settes automatisk basert på displayName
        'status' => [
            'active_deployed' => env('PURESERVICE_COMPUTER_STATUS_DEPLOYED', 'Tildelt bruker'),
            'active_inStorage' => env('PURESERVICE_COMPUTER_STATUS_IN_STORAGE', 'På lager'),
            'active_phaseOut' => env('PURESERVICE_COMPUTER_STATUS_PHASEOUT', 'Under utfasing'),
            'inactive_reused' => env('PURESERVICE_COMPUTER_STATUS_REUSED', 'Sendt til ombruk'),
            'inactive_recycled' => env('PURESERVICE_COMPUTER_STATUS_RECYCLED', 'Sendt til gjenvinning'),
            'inactive_stolen' => env('PURESERVICE_COMPUTER_STATUS_STOLEN', 'Stjålet'),
            'inactive_lost' => env('PURESERVICE_COMPUTER_STATUS_LOST', 'Mistet'),
            'inactive_service' => env('PURESERVICE_COMPUTER_STATUS_SERVICE', 'Sendt til service'),
            'inactive_phasedOut' => env('PURESERVICE_COMPUTER_STATUS_PHASEDOUT', 'Utfasing - innlevert'),
        ],
    ],
    'mobile' => [
        'displayName' => env('PURESERVICE_MOBILE_ASSETTYPE_NAME', 'Mobilenhet'),
        'lifespan' => env('PURESERVICE_MOBILE_LIFESPAN', 3), // Forventet levetid, oppgitt som år
        'asset_type_id' => null, // Settes automatisk basert på displayName
        'relationship_type_id' => null, // Settes automatisk basert på displayName
        'className' => '', // Settes automatisk basert på displayName
        'fields' => [
            'name' => env('PURESERVICE_MOBILE_FIELD_NAME', 'Navn'),
            'serial' => env('PURESERVICE_MOBILE_FIELD_SERIAL', 'Serienr'),
            'model' => env('PURESERVICE_MOBILE_FIELD_MODEL', 'Modell'),
            'modelId' => env('PURESERVICE_MOBILE_FIELD_MODELID', 'ModelID'),
            'OsVersion' => env('PURESERVICE_MOBILE_FIELD_OS', 'OS-versjon'),
            'jamfUrl' => env('PURESERVICE_MOBILE_FIELD_JAMFURL', 'Jamf-URL'),
            'lastSeen' => env('PURESERVICE_MOBILE_FIELD_LASTSEEN', 'Sist sett'),
            'memberSince' => env('PURESERVICE_MOBILE_FIELD_MEMBERSINCE', 'Innmeldt'),
            'EOL' => env('PURESERVICE_MOBILE_FIELD_EOL', 'EOL'),
        ],
        'properties' => [], // Settes automatisk basert på displayName
        'status' => [
            'active_deployed' => env('PURESERVICE_MOBILE_STATUS_DEPLOYED', 'Tildelt bruker'),
            'active_inStorage' => env('PURESERVICE_MOBILE_STATUS_IN_STORAGE', 'På lager'),
            'active_phaseOut' => env('PURESERVICE_MOBILE_STATUS_PHASEOUT', 'Under utfasing'),
            'inactive_reused' => env('PURESERVICE_MOBILE_STATUS_REUSED', 'Sendt til ombruk'),
            'inactive_recycled' => env('PURESERVICE_MOBILE_STATUS_RECYCLED', 'Sendt til gjenvinning'),
            'inactive_stolen' => env('PURESERVICE_MOBILE_STATUS_STOLEN', 'Stjålet'),
            'inactive_lost' => env('PURESERVICE_MOBILE_STATUS_LOST', 'Mistet'),
            'inactive_service' => env('PURESERVICE_MOBILE_STATUS_SERVICE', 'Sendt til service'),
            'inactive_phasedOut' => env('PURESERVICE_MOBILE_STATUS_PHASEDOUT', 'Utfasing - innlevert'),
        ],
    ],
    'comms' => [
        'in' => 1,
        'out' => 2,
        'internal' => 1,
        'standard' => 2,
        'direction' => [
            'in' => 1,
            'out' => 2,
            'internal' => 3,
        ],
    ],
    'visibility' => [
        'visible' => 0,
        'no_receipt' => 1,
        'invisible' => 2,
    ],
    'ticket' => [
        'source' => env('PS_SOURCE', 'SvarUt'),
        'zone' => env('PS_ZONE', 'Dispatchers'),
        'team' => env('PS_TEAM', 'Dispatcher'),
        'visibility' => env('PS_VISIBILITY', 2),
        'ticketType' => env('PS_TICKET_TYPE', 'Henvendelse'),
        'priority' => env('PS_PRIORITY', 'Normal'),
        'status' => env('PS_STATUS', 'Ny'),
        'requestType' => env('PS_REQUEST_TYPE','Ticket'),
    ],
    'user' => [
        'role_id' => env('PS_USER_ROLE_ID', 10),
    ],
];
