<?php

/**
 * Innstillinger som brukes av Services\Enhetsregisteret
 */

 return [
    'api' => [
        'url' => 'https://data.brreg.no',
        'prefix' => '/enhetsregisteret/api/',
        'accept' => 'application/vnd.brreg.enhetsregisteret.enhet.v2+json;charset=UTF-8',
    ],
    'search' => [
        'alleKommuner' => ['organisasjonsform' => 'KOMM', 'size' => 400],
        'alleFylkeskommuner' => ['organisasjonsform' => 'FYLK', 'size' => 100],
        'alleStatlige' => ['organisasjonsform' => 'STAT', 'size' => 50],
    ],
    'underliggende' => ['overordnetEnhet'=>'[ORGNR]', 'size' => 1000],
 ];
