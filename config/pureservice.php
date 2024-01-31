<?php

return [
    'api_url' => env('PURESERVICE_URL', null),
    'apikey' =>  env('PURESERVICE_APIKEY', null),
    'api' => [
        'url' => env('PURESERVICE_URL', null),
        'prefix' => env('PURESERVICE_PREFIX', '/agent/api'),
        'token' => env('PURESERVICE_APIKEY', null),
        'auth' => 'token',
        'accept' => 'application/vnd.api+json',
        'contentType' => 'application/vnd.api+json',
        'dlPath' => env('PURESERVICE_TMP_PATH', 'pureservice'),
        'timeout' => 90,
    ],
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
        'internal' => 1,
        'standard' => 2,
        'description' => 7,
        'history' => 6,
        'solution' => 50,
        'custom' => 9,
        'direction' => [
            'in' => 1,
            'out' => 2,
            'internal' => 3,
        ],
        'visibility' => [
            'off' => 0,
            'on' => 1,
        ],
    ],
    'email' => [
        'status' => [
            'received' => 0,
            'sent' => 4,
            'failed' => 5,
        ],
    ],
    'visibility' => [
        'visible' => 0,
        'no_receipt' => 1,
        'invisible' => 2,
    ],
    'ticket' => [
        'codeTemplate' => env('PURESERVICE_TICKET_NUMBER_TEMPLATE', '[Sak ID# {{RequestNumber}}]'),
        'source' => env('PURESERVICE_TICKET_SOURCE', 'SvarUt'),
        'zone' => env('PURESERVICE_TICKET_ZONE', 'Fordeling'),
        'team' => env('PURESERVICE_TICKET_TEAM', 'Postmottak'),
        'visibility' => env('PURESERVICE_TICKET_VISIBILITY', 2),
        'ticketType' => env('PURESERVICE_TICKET_TYPE', 'Henvendelse'),
        'priority' => env('PURESERVICE_TICKET_PRIORITY', 'Normal'),
        'status' => env('PURESERVICE_TICKET_STATUS', 'Ny'),
        'status_solved' => env('PURESERVICE_TICKET_SOLVED_STATUS', 'Løst'),
        'status_closed' => env('PURESERVICE_TICKET_CLOSED_STATUS', 'Lukket'),
        'status_in_progress' => env('PURESERVICE_TICKET_STATUS_OPEN', 'Under arbeid'),
        'status_message_sent' => env('PURESERVICE_TICKET_STATUS_SENT', 'Venter - sluttbruker'),
        'requestType' => env('PURESERVICE_TICKET_REQUEST_TYPE','Ticket'),
    ],
    'innsynskrav' => [
        // Prefikset med 'DPE_' i miljøvariablene
        'codeTemplate' => env('PURESERVICE_TICKET_NUMBER_TEMPLATE', '[Sak ID# {{RequestNumber}}]'),
        'source' => env('DPE_TICKET_SOURCE', 'eFormidling'),
        'zone' => env('DPE_TICKET_ZONE', env('PURESERVICE_TICKET_ZONE', 'Fordeling')),
        'team' => env('DPE_TICKET_TEAM', env('PURESERVICE_TICKET_TEAM', 'Postmottak')),
        'visibility' => env('DPE_TICKET_VISIBILITY', 1),
        'ticketType' => env('DPE_TICKET_TYPE', 'Innsynskrav'),
        'priority' => env('DPE_TICKET_PRIORITY', 'Høy'),
        'status' => env('DPE_TICKET_STATUS', env('PURESERVICE_TICKET_STATUS', 'Ny')),
        'status_solved' => env('DPE_TICKET_SOLVED_STATUS', env('PURESERVICE_TICKET_SOLVED_STATUS', 'Løst')),
        'requestType' => env('PURESERVICE_TICKET_REQUEST_TYPE','Ticket'),
    ],
    'eformidling' => [
        // Prefikset med 'EF_' i miljøvariablene
        'codeTemplate' => env('PURESERVICE_TICKET_NUMBER_TEMPLATE', '[Sak ID# {{RequestNumber}}]'),
        'source' => env('EF_TICKET_SOURCE', 'eFormidling'),
        'zone' => env('EF_TICKET_ZONE', env('PURESERVICE_TICKET_ZONE', 'Fordeling')),
        'team' => env('EF_TICKET_TEAM', env('PURESERVICE_TICKET_TEAM', 'Postmottak')),
        'visibility' => env('EF_TICKET_VISIBILITY', 2),
        'ticketType' => env('EF_TICKET_TYPE', env('PURESERVICE_TICKET_TYPE', 'Henvendelse')),
        'priority' => env('EF_TICKET_PRIORITY', env('PURESERVICE_TICKET_PRIORITY', 'Normal')),
        'status' => env('EF_TICKET_STATUS', env('PURESERVICE_TICKET_STATUS', 'Ny')),
        'status_solved' => env('EF_TICKET_SOLVED_STATUS', env('PURESERVICE_TICKET_SOLVED_STATUS', 'Løst')),
        'requestType' => env('PURESERVICE_TICKET_REQUEST_TYPE','Ticket'),
    ],

    'user' => [
        'role_id' => env('PURESERVICE_USER_ROLE_ID', 10),
        'no_email_field' => env('PURESERVICE_USER_NOEMAIL_FIELD', false),
        'dummydomain' => env('PURESERVICE_USER_DUMMYDOMAIN', 'svarut.pureservice.local'),
        'ef_domain' => env('PURESERVICE_EF_DOMAIN', 'eformidling.pureservice.local'),
    ],
    'company' => [
        'categoryfield' => env('PURESERVICE_COMPANY_CATEGORY_FIELD', false),
        'categoryMap' => [
            'KOMM' => 'Kommune',
            'STAT' => 'Statlig virksomhet',
            'FYLK' => 'Fylkeskommune',
            'ORGL' => 'Underliggende statlig virksomhet',
        ],
        'name_overrides' => [
            '872417982' => 'Herøy kommune (Nordland)',
            '871034222' => 'Våler kommune (Innlandet)',
        ],
    ],
    // Oppsett for Utsending
    'dispatch' => [
        'ef_domain' => env('PURESERVICE_EF_DOMAIN', 'pureservice.local'),
        'address' => [
            'ef' => env('PURESERVICE_DISPATCH_EF', 'ut@eformidling.pureservice.local'),
            'email' => env('PURESERVICE_DISPATCH_EMAIL', 'ut@e-post.pureservice.local'),
            'email_121' => env('PURESERVICE_DISPATCH_EMAIL_121', 'ut-121@e-post.pureservice.local'),
        ],
        'finishStatus' => env('PURESERVICE_DISPATCH_SOLVED_STATUS', 'Løst'),
        'status_in_progress' => env('PURESERVICE_TICKET_STATUS_OPEN', 'Under arbeid'),
        'assetTypeName' => env('PURESERVICE_DISPATCH_LIST_ASSETNAME', 'Mottakerliste'),
        'listRelationName' => [
            'toCompany' => env('PURESERVICE_DISPATCH_LINK_TO_COMPANY', 'Inneholder firma'),
            'toUser' => env('PURESERVICE_DISPATCH_LINK_TO_USER', 'Inneholder bruker'),
            'toTicket' => env('PURESERVICE_DISPATCH_LINK_TO_TICKET', 'Gir mottakere til'),
        ],
    ],

    // Overstyrer domenemapping (PsUserCleanup)
    // domain settes til e-postdomenet (det etter @)
    // company settes til navnet på firmaet som er registrert i Pureservice
    // company kan settes til false hvis domenet skal ignoreres
    'domainmapping' => [
        [
            'domain' => 'bergen.kommune.no',
            'company' => 'Bergen Kommune',
        ],
        [
            'domain' => 'statsforvalteren.no',
            'company' => false,
        ],
        [
            'domain' => 'news.cms.law',
            'company' => 'CMS Kluge Advokatfirma AS',
        ],
        [
            'domain' => 'inn.innsyn.no',
            'company' => 'Faktisk.no AS',
        ],
        [
            'domain' => 'epost.no',
            'company' => false,
        ],
        [
            'domain' => 'online.no',
            'company' => false,
        ],
        [
            'domain' => 'live.no',
            'company' => false,
        ],
        [
            'domain' => 'gmail.com',
            'company' => false,
        ],
        [
            'domain' => 'outlook.com',
            'company' => false,
        ],
        [
            'domain' => 'email.ru',
            'company' => false,
        ],
        [
            'domain' => 'altibox.no',
            'company' => false,
        ],
    ],
    'yearlystats' => [
        [
            'title' => 'Saker opprettet totalt',
            'uri' => '/ticket/count/',
            'params' => [
                'filter' => 'created.Year == *YEAR* AND !user.emailaddress.email.contains("dibk.no")'
            ],
        ],
        [
            'title' => 'Saker opprettet SG',
            'uri' => 'ticket/count/',
            'params' => [
                'filter' => 'created.Year == *YEAR* AND !user.emailaddress.email.contains("dibk.no") AND assignedTeam.name == "Sentral godkjenning"'
            ],
        ],
        [
            'title' => 'Utgående brev fra Pureservice',
            'uri' => '/email/count/',
            'params' => [
                'filter' => 'created.Year == *YEAR* AND from == "post@dibk.no" AND !to.contains("dibk.no")',
            ],
        ],
        [
            'title' => 'Innkommende e-post til post@dibk.no',
            'uri' => '/email/count/',
            'params' => [
                'filter' => 'created.Year == *YEAR* AND to == "post@dibk.no" AND !from.contains("dibk.no")',
            ],
        ],
    ],
];
