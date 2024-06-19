# Tilleggsfuksjoner for Pureservice

Vi har bygd en kommandolinje-basert Laravel-applikasjon for å utvide funksjonaliteten til [Pureservice](https://www.pureservice.com):

- [jamf2pureservice](docs/jamf2pureservice.md) - synkroniserer maskiner og mobilenheter i Jamf Pro med ressurser i Pureservice
- [eFormidling](docs/eformidling.md) - Henter inn meldinger gjennom integrasjonspunkt fra Altinn, eInnsyn og SvarUt inn i Pureservice
- [utsendelse](docs/utsendelse.md) - Håndterer masseutsendelser og utgående eFormidling fra Pureservice
- [offentlige2ps](docs/offentlige2ps.md) - Importerer kommuner og statlige instanser fra BRREG inn som firma og sluttbrukere i Pureservice
- [cleanUpUsers](docs/cleanupusers.md) - Går gjennom sluttbrukere og korrigerer brukerinfo

## What are you saying?

We're sorry if you don't understand the Norwegian language. This repository contains command line functions that expands the functionality of the Norwegian helpdesk system [Pureservice](https://www.pureservice.com). The extra functionality is mostly based on services not available outside Norway. The exception is [jamf2pureservice](docs/jamf2pureservice.md), which will sync data from Jamf Pro with Pureservice Assets.

## Utdaterte funksjoner (kan virke fremdeles)

- [svarinn2pureservice](docs/svarinn2pureservice.md) - Fungerer som SvarUt-mottak for Pureservice (erstattet av [eFormidling](docs/eformidling.md))

## Under utvikling

Vi videreutvikler og fikser stadig på koden til sync2pureservice. Følgende er under utvikling:

- Masseutsendelse som oppretter enkeltsaker i Pureservice (scenario 2)

## Systemkrav for kjøring

- [PHP 8.x](https://php.net)
- [Composer](https://getcomposer.org/)
- PHP-tilleggene gd, opcache og zip


## Kjøring i Github Actions (evt. Bitbucket Pipelines)

Vi har lagt opp til at funksjonene skal kunne kjøres i Github Actions og Bitbucket Pipelines. Kildekoden inneholder våre GitHub Actions og en bitbucket-pipelines.yml, som kan brukes som eksempler for å sette opp sin egen sync2pureservice. Da kan vi enten kjøre det direkte i et shell (så lenge systemkravene er i orden) eller inne i en konteiner. Vårt egenkomponert Docker-image, [dibk/sync2pureservice](https://hub.docker.com/r/dibk/sync2pureservice) inneholder korrekt PHP-versjon (8.x) og de tilleggene vi trenger (gd, opcache og zip, samt composer). Vårt image bygges fra vår [Dockerfile](Dockerfile) hver søndag, og er tilgjengelig både for AMD64 og ARM64.

Det er også fullt mulig å kjøre sync2pureservice på det offisielle [php:alpine fra Docker Hub](https://hub.docker.com/_/php). I så fall kan du bruke [installasjonsskriptet](scripts/php-install-alpine.sh) til å installere de nødvendige tilleggene før kjøring. Det tar et par minutter å sette opp, så vi vil anbefale å bruke vårt ferdige eller ditt eget ferdigbygde. Alt er tilgjengelig i kildekoden.

## Kjøring på egen maskin

Her kan vi ikke tilby så mye hjelp, det må være opptil hver enkelt å gjøre dette oppsettet slik man vil ha det. Generelt vil det gå på noe slikt:

1. Bruk git clone eller last ned kildekoden til din maskin
1. Opprett en .env-fil ved å kopiere .env.example til .env og sette dine egne innstillinger (f.eks. `cp .env.example .env`). Det er en hel haug med miljøvariabler som kan settes i .env eller som miljøvariabler før kjøring. Nærmere info om dette under hver enkelt funksjon
1. Kjør `composer install` for å installere nødvendige rammeverk
1. Kjør `php artisan migrate:fresh` for å klargjøre den lokale databasen (eller om du vil bruke ditt eget databaseoppsett spesifisert i [config/database.php](config/database.php))
1. Du er nå klar for å kjøre sync2pureservice (gitt at .env inneholder det du trenger)

# Lisens
Dette prosjektet publiseres som åpen kildekode lisensiert under [MIT license](https://opensource.org/licenses/MIT).
