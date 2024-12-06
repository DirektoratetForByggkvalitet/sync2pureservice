<?php

return [
  'notify' => [
    'days' => 30,
    'subject' => env('SECRET_CHECK_SUBJECT', '? utløper for App Registration ?'),
    'refField' => env('SECRET_CHECK_ID_FIELD', 'customerReference'),
    'refPrefix' => env('SECRET_CHECK_REF_PREFIX', 'AppID_'),
    'from' => env('SECRET_CHECK_EMAIL', env('MAIL_FROM_ADDRESS')),
    'categories' => env('SECRET_CHECK_CATEGORIES'),
  ],
  'template' => [
    'note' => 'notifications.expiry_note',
    'ticket' => 'notifications.expiry_ticket',
  ],
  'credentials' => [
    'tenantId' => env('AZURE_TENANT_ID'),
    'clientId' => env('AZURE_APP_ID'),
    'certificatePath' => env('AZURE_CERT_PATH', storage_path('graph_client.pem')),
    'privateKeyPath' => env('AZURE_KEY_PATH', storage_path('graph_client.pem')),
  ],
  'scopes' => [
    'https://graph.microsoft.com/.default'
  ],
];