<?php

return [
    'file' => env('SVARINN_EXCEL_LOOKUP_FILE', storage_path('kommuner.xlsx')),
    'typesInFile' => [
        'KOMM',
        'FYLK'
    ],
    'map' => [
        'A' => 'knr',
        'B' => 'navn',
        'C' => 'adresse',
        'D' => 'postnr',
        'E' => 'poststed',
        'F' => 'e-post'
    ],
];
