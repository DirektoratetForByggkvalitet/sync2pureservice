<?php

return [
    'file' => env('SVARINN_EXCEL_LOOKUP_FILE', storage_path('virksomheter.xlsx')),
    'typesInFile' => [
        'KOMM',
        'FYLK',
        'STAT',
        'ORGL'
    ],
    'map' => [
        'A' => 'regnr',
        'B' => 'knr',
        'C' => 'navn',
        'D' => 'e-post',
        'E' => 'nettside',
        'F' => 'kategori',
        'G' => 'notater'
    ],
];
