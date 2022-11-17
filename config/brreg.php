<?php
return [
    'url' => env('BRREG_API_URL', 'https://data.brreg.no/enhetsregisteret/api'),
    'orgtyper' => array_map('trim', explode(',', env('BRREG_SYNK_ORGTYPER', ''))), // Kommaseparert liste til array
    'underliggende' => array_map('trim', explode(',', env('BRREG_SYNK_UNDERORDNET', ''))), // Kommaseparert liste til array
];
