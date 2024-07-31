# Tilleggsfuksjoner for Pureservice

Vi har bygd en kommandolinje-basert [Laravel Zero](https://laravel-zero.com)-applikasjon for å utvide funksjonaliteten til [Pureservice](https://www.pureservice.com):

- [jamf2pureservice](docs/jamf2pureservice.md) - synkroniserer maskiner og mobilenheter i Jamf Pro med ressurser i Pureservice
- [eFormidling](docs/eformidling.md) - Henter inn meldinger gjennom integrasjonspunkt fra Altinn, eInnsyn og SvarUt inn i Pureservice
- [utsendelse](docs/utsendelse.md) - Håndterer masseutsendelser og utgående eFormidling fra Pureservice
- [offentlige2ps](docs/offentlige2ps.md) - Importerer kommuner og statlige instanser fra BRREG inn som firma og sluttbrukere i Pureservice
- [cleanUpUsers](docs/cleanupusers.md) - Går gjennom sluttbrukere og korrigerer brukerinfo

## In English

This repository contains command line functions that expands the functionality of the Norwegian helpdesk system [Pureservice](https://www.pureservice.com). The functionality is mostly based on services not available outside Norway. A possible exception is [jamf2pureservice](docs/jamf2pureservice.md), which will sync data from Jamf Pro with Pureservice Assets. All documentation for sync2pureservice is and will remain in Norwegian.

## Under utvikling

Vi videreutvikler og fikser stadig på koden til sync2pureservice. Følgende er under utvikling (men ligger litt bak i køen):

- Masseutsendelse som oppretter enkeltsaker i Pureservice (scenario 2)

## Systemkrav for kjøring

- [PHP 8.x](https://php.net)
- [Composer](https://getcomposer.org/)
- PHP-tilleggene gd, sqlite3, opcache og zip. Tillegget igbinary er anbefalt hvis du bruker redis.

### Database

Det er ikke nødvendig å koble løsningen til en database-tjener. Løsningen bruker en Sqlite3-database for å mellomlagre data for tyngre oppgaver. Data i databasen er ikke ment å være tilgjengelige, databasen bør nullstilles for hver funksjon som kjøres. 

### Oppsett for mellomlagring

Du kan også se på [cache-oppsett](docs/caching.md) for Laravel, men det er ikke påkrevd.

## Miljøvariabler

Det er mange miljøvariabler som må til for å integrere mellom ulike systemer og Pureservice. For i det hele tatt å kunne snakke med Pureservice må vi ha disse:

| Variabel | Kommentar |
|----|----|
| PURESERVICE_URL | Denne må settes til din instans av Pureservice, typisk https://firma.pureservice.com |
| PURESERVICE_APIKEY | Du må opprette en API-nøkkel i Pureservice og oppgi den her for at sync2pureservice skal ha tilgang til å snakke med Pureservice |

## Kjøring i Github Actions (evt. Bitbucket Pipelines)

Vi har lagt opp til at funksjonene skal kunne kjøres i Github Actions og Bitbucket Pipelines. Kildekoden inneholder våre GitHub Actions og en bitbucket-pipelines.yml, som kan brukes som eksempler for å sette opp sin egen sync2pureservice. Da kan vi enten kjøre det direkte i et shell (så lenge systemkravene er i orden) eller inne i en konteiner. Vårt egenkomponert Docker-image, [dibk/sync2pureservice](https://hub.docker.com/r/dibk/sync2pureservice) inneholder korrekt PHP-versjon (8.x) og de tilleggene vi trenger (gd, opcache og zip, samt composer). Vårt image bygges fra vår [build-docker.yml](.github/workflows/build-docker.yml) hver søndag, og bygges både for AMD64 og ARM64.

Det er også fullt mulig å kjøre sync2pureservice på det offisielle [php:alpine fra Docker Hub](https://hub.docker.com/_/php). I så fall kan du bruke [installasjonsskriptet](scripts/php-install-alpine.sh) til å installere de nødvendige tilleggene før kjøring. Det tar et par minutter å sette opp, så vi vil anbefale å bruke vårt eller ditt eget ferdigbygde image.

Vi har lagt opp Github Actions (og Bitbucket Pipelines) til å kjøre på 'self-hosted runners', altså maskiner som vi selv har satt opp og vedlikeholder. Dette har vi gjort for å kunne sikre tilgangen til vår Redis-instans i Azure bedre. Det er fullt mulig å kjøre dette på Github eller Bitbucket sine runners, f.eks. ved å kjøre i en PHP-konteiner eller bruke f.eks. [Setup PHP](https://github.com/marketplace/actions/setup-php-action) til å sette opp en Linux- eller macOS-basert GitHub-runner.

## Kjøring på egen maskin

Det er selvsagt også mulig å kjøre dette direkte på egen maskin. Her kan vi ikke tilby så mye hjelp, det må være opptil hver enkelt å gjøre dette oppsettet slik man vil ha det. Generelt vil det gå på noe slikt:

1. Bruk git clone eller last ned kildekoden til din maskin
1. Opprett en .env-fil ved å kopiere .env.example til .env og sette dine egne innstillinger (f.eks. `cp .env.example .env`). Det er en hel haug med miljøvariabler som kan settes i .env eller som miljøvariabler før kjøring. Nærmere info om dette under hver enkelt funksjon
1. Kjør `composer install` for å installere nødvendige rammeverk
1. Kjør `./sync2pureservice migrate:fresh` for å klargjøre den lokale databasen (eller om du vil klargjøre ditt eget databaseoppsett spesifisert i [config/database.php](config/database.php))
1. Du er nå klar for å kjøre sync2pureservice (gitt at .env inneholder det du trenger)

# Lisens
Dette prosjektet publiseres som åpen kildekode lisensiert under [MIT license](https://opensource.org/licenses/MIT).
