# Tilleggsfuksjoner for Pureservice

Vi har bygd en kommandolinje-basert Laravel-applikasjon for å utvide funksjonaliteten i Pureservice:

- [jamf2pureservice](docs/jamf2pureservice.md) - synkroniserer maskiner og mobilenheter i Jamf Pro med ressurser i Pureservice
- [svarinn2pureservice](docs/svarinn2pureservice.md) - fungerer som SvarUt-mottak for Pureservice
- [eFormidling](docs/eformidling.md) - Henter inn meldinger gjennom integrasjonspunkt fra Altinn, eInnsyn og SvarUt inn i Pureservice
- [offentlige2ps](docs/offentlige2ps.md) - importerer kommuner og statlige instanser inn som firma og sluttbrukere i Pureservice
- [utsendelse](docs/utsendelse.md) - håndterer masseutsendelser fra Pureservice

## Kjøring i Bitbucket Pipelines

Vi har lagt opp til at funksjonene kan kjøres i Bitbucket Pipelines. Da bruker vi et egenkomponert Docker-image, [dibk/sync2pureservice](https://hub.docker.com/r/dibk/sync2pureservice), som inneholder korrekt PHP-versjon (8.2.x) og de tilleggene vi trenger (gd, opcache og zip, samt composer). Vårt image bygges fra denne koden hver søndag, og er tilgjengelig både for AMD64 og ARM64.

Det er også fullt mulig å kjøre sync2pureservice med det offisielle [php:alpine fra Docker Hub](https://hub.docker.com/_/php). I så fall kan du bruke [installasjonsskriptet](scripts/php-install-alpine.sh) til å installere de nødvendige tilleggene før kjøring. Det tar et par minutter å sette opp, så vi vil anbefale å bruke vårt ferdige eller ditt eget ferdigbygde. Alt er tilgjengelig i kildekoden.

## Kjøring på egen maskin

Det er fullt mulig å kjøre skriptene lokalt på egen maskin. I så fall trenger du et oppsett som inneholder følgende:

- [PHP 8.2.x](https://php.net)
- [Composer](https://getcomposer.org/)
- PHP-tilleggene gd, opcache og zip

Her kan vi ikke tilby så mye hjelp, det må være opptil hver enkelt å gjøre dette oppsettet slik man vil ha det. Generelt vil det gå på noe slikt:

1. Bruk git clone eller last ned kildekoden til din maskin
1. Opprett en .env-fil ved å kopiere .env.example til .env og sette dine egne innstillinger (f.eks. `cp .env.example .env`)
1. Kjør `composer install` for å installere nødvendige rammeverk
1. Kjør `php artisan migrate:fresh` for å klargjøre den lokale databasen (eller om du vil bruke ditt eget databaseoppsett spesifisert i [config/database.php](config/database.php))
1. Du er nå klar for å kjøre sync2pureservice (gitt at .env inneholder det du trenger)

# Lisens
Dette prosjektet publiseres som åpen kildekode lisensiert under [MIT license](https://opensource.org/licenses/MIT).
