<?php

return [
    'file' => env('SVARINN_EXCEL_LOOKUP_FILE', base_path('kommuner.xlsx')),
    'map' => [
        'A' => 'knr',
        'B' => 'navn',
        'C' => 'adresse',
        'D' => 'postnr',
        'E' => 'poststed',
        'F' => 'e-post'
    ],
];
