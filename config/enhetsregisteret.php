<?php

/**
 * Innstillinger som brukes av Services\Enhetsregisteret
 */

 return [
    'api' => [
        'url' => 'https://data.brreg.no',
        'prefix' => '/enhetsregisteret/api/',
    ],
    'search' => [
        'alleKommuner' => 'enheter?organisasjonsform=KOMM&size=400',
        'alleFylkeskommuner' => 'enheter?organisasjonsform=FYLK&size=100',
        'alleStatlige' => 'enheter?organisasjonsform=STAT&size=50',
    ],
    'underliggende' => 'enheter?overordnetEnhet=[ORGNR]&size=1000',
 ];
