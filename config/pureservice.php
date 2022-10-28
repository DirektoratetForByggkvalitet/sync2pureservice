<?php

return [
  'api_url' => env('PURESERVICE_URL'),
  'apikey' =>  env('PURESERVICE_APIKEY'),
  'computer' => [
    'displayName' => env('PURESERVICE_COMPUTER_ASSETTYPE_NAME', 'Datamaskin'),
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
        'inactive_lost' => env('PURESERVICE_COMPUTER_STATUS_STOLEN', 'Mistet'),
        'inactive_service' => env('PURESERVICE_COMPUTER_STATUS_SERVICE', 'Sendt til service'),
        'inactive_phasedOut' => env('PURESERVICE_COMPUTER_STATUS_PHASEDOUT', 'Utfasing - innlevert'),
    ],
  ],
  'mobile' => [
    'displayName' => env('PURESERVICE_MOBILE_ASSETTYPE_NAME', 'Mobilenhet'),
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
        'inactive_lost' => env('PURESERVICE_MOBILE_STATUS_STOLEN', 'Mistet'),
        'inactive_service' => env('PURESERVICE_MOBILE_STATUS_SERVICE', 'Sendt til service'),
        'inactive_phasedOut' => env('PURESERVICE_MOBILE_STATUS_PHASEDOUT', 'Utfasing - innlevert'),
    ],
  ],
];
