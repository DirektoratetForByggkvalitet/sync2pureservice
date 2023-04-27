<?php

/**
 * Innstillinger som brukes av Services\Enhetsregisteret
 */

 return [
    'api_url' => 'https://data.brreg.no',
    'prefix' => '/enhetsregisteret/api/enheter',
    'search' => [
        'alleKommuner' => '?organisasjonsform=KOMM&size=400',
        'alleFylkesKommuner' => '?organisasjonsform=FYLK&size=100',
        'alleDep' => '?organisasjonsform=STAT&size=50',
    ],
    'underliggende' => '?overordnetEnhet=[ORGNR]&size=1000',
 ];
