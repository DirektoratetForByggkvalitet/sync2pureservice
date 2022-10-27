<?php

return [
  'api_url' => env('PURESERVICE_URL'),
  'apikey' =>  env('PURESERVICE_APIKEY'),
  'field_prefix' => 'assets_UDF_95_',
  'computer' => [
    'displayName' => env('PURESERVICE_COMPUTER_DISPLAYNAME', 'Datamaskin'),
    'asset_type_id' => null,
    'relationship_type_id' => null,
    'className' => '',
    'status' => [
        'active_deployed' => env('PURESERVICE_COMPUTER_STATUS_DEPLOYED', 'Tildelt bruker'),
        'active_inStorage' => env('PURESERVICE_COMPUTER_STATUS_IN_STORAGE', 'På lager'),
        'active_phaseOut' => env('PURESERVICE_COMPUTER_STATUS_PHASEOUT', 'Under utfasing'),
        'inactive_reused' => env('PURESERVICE_COMPUTER_STATUS_REUSED', 'Sendt til ombruk'),
        'inactive_recycled' => env('PURESERVICE_COMPUTER_STATUS_RECYCLED', 'Sendt til gjenvinning'),
        'inactive_stolen' => env('PURESERVICE_COMPUTER_STATUS_STOLEN', 'Stjålet'),
        'inactive_lost' => env('PURESERVICE_COMPUTER_STATUS_STOLEN', 'Mistet'),
        'inactive_service' => env('PURESERVICE_COMPUTER_STATUS_SERVICE', 'Sendt til service'),
        'inactive_phasedOut' => env('PURESERVICE_COMPUTER_STATUS_PHASEDOUT', 'Utfasing - innlevert'),
    ],
  ],
  'mobile' => [
    'displayName' => env('PURESERVICE_MOBILE_DISPLAYNAME', 'Mobilenhet'),
    'asset_type_id' => null,
    'relationship_type_id' => 4,
    'className' => '',
    'status' => [
        'active_deployed' => env('PURESERVICE_MOBILE_STATUS_DEPLOYED', 'Tildelt bruker'),
        'active_inStorage' => env('PURESERVICE_MOBILE_STATUS_IN_STORAGE', 'På lager'),
        'active_phaseOut' => env('PURESERVICE_MOBILE_STATUS_PHASEOUT', 'Under utfasing'),
        'inactive_reused' => env('PURESERVICE_MOBILE_STATUS_REUSED', 'Sendt til ombruk'),
        'inactive_recycled' => env('PURESERVICE_MOBILE_STATUS_RECYCLED', 'Sendt til gjenvinning'),
        'inactive_stolen' => env('PURESERVICE_MOBILE_STATUS_STOLEN', 'Stjålet'),
        'inactive_lost' => env('PURESERVICE_MOBILE_STATUS_STOLEN', 'Mistet'),
        'inactive_service' => env('PURESERVICE_MOBILE_STATUS_SERVICE', 'Sendt til service'),
        'inactive_phasedOut' => env('PURESERVICE_MOBILE_STATUS_PHASEDOUT', 'Utfasing - innlevert'),
    ],
  ],
];
