<?php

/**
 * Innstillinger som brukes av Services\Enhetsregisteret
 */

 return [
    'api' => [
        'url' => 'https://data.brreg.no',
        'prefix' => '/enhetsregisteret/api/enheter',
    ],
    'search' => [
        'alleKommuner' => '?organisasjonsform=KOMM&size=400',
        'alleFylkeskommuner' => '?organisasjonsform=FYLK&size=100',
        'alleStatlige' => '?organisasjonsform=STAT&size=50',
    ],
    'underliggende' => '?overordnetEnhet=[ORGNR]&size=1000',
 ];
