<?php

return [
  'api_url' => env('PURESERVICE_URL'),
  'apikey' =>  env('PURESERVICE_APIKEY'),
  'field_prefix' => 'assets_UDF_95_',
  'asset_type_id' => env('PURESERVICE_ASSETTYPE_ID', 1),
  'className' => '_'.env('PURESERVICE_ASSETTYPE_ID', 1).'_Assets_'.env('PURESERVICE_ASSETTYPE_NAME'),
  'status' => [
    'active_deployed' => env('PURESERVICE_STATUS_DEPLOYED', 'Tildelt bruker'),
    'active_inStorage' => env('PURESERVICE_STATUS_IN_STORAGE', 'På lager'),
    'active_phaseOut' => env('PURESERVICE_STATUS_PHASEOUT', 'Under utfasing'),
    'inactive_reused' => env('PURESERVICE_STATUS_REUSED', 'Sendt til ombruk'),
    'inactive_recycled' => env('PURESERVICE_STATUS_RECYCLED', 'Sendt til gjenvinning'),
    'inactive_stolen' => env('PURESERVICE_STATUS_STOLEN', 'Stjålet'),
    'inactive_lost' => env('PURESERVICE_STATUS_STOLEN', 'Mistet'),
  ],
];
