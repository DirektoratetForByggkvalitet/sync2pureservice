# Tilleggsfuksjoner for Pureservice

Vi har bygd en kommandolinje-basert Laravel-applikasjon for å utvide funksjonaliteten i Pureservice:

- [jamf2pureservice](docs/jamf2pureservice.md) - synkroniserer maskiner og mobilenheter i Jamf Pro med ressurser i Pureservice
- [svarinn2pureservice](docs/svarinn2pureservice.md) - fungerer som SvarUt-mottak for Pureservice
- [utsendelse](docs/utsendelse.md) - kan håndtere masseutsendelser fra Pureservice

## Kjøring i Bitbucket Pipelines

Vi har lagt opp til at funksjonene kan kjøres i Bitbucket Pipelines. I så tilfelle benyttes det offisielle PHP-imaget php:fpm-alpine, i tillegg til et skript som installerer et par PHP-tillegg (gd, opcache og zip, samt composer) før funksjonene kjøres. Oppsettet er lagt inn i [bitbucket-pipelines.yml](bitbucket-pipelines.yml). Det betyr at pipelines kan kjøres fra offisielt PHP-image.

# Lisens
Dette prosjektet publiseres som åpen kildekode lisensiert under [MIT license](https://opensource.org/licenses/MIT).
