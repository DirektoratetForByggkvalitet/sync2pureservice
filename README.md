# Jamf2Pureservice

** Dokumentasjonen er under oppretting… **

Dette er en noe unødvendig stor Laravel-installasjon ment for å synke [Jamf Pro](https://jamf.com) med [Pureservice Assets](https://pureservice.com).
# Hva gjør jamf2pureservice?

Ferdig installert vil jamf2pureservice tilby én kommandolinje-kommando som utfører følgende operasjoner:
1. Kobler til Jamd Pro og henter inn informasjon om alle maskiner og mobilenheter
2. Kobler til Pureservice og henter inn informasjon om alle maskiner og mobilenheter
3. Går gjennom alle enheter fra Jamf Pro
    - Regner ut EOL og bestemmer status på enheter som skal fases ut
    - Oppdaterer/oppretter dem i Pureservice
    - Kobler enheter i Pureservice mot brukere, slik det er registrert i Jamf Pro
4. Ser etter enheter i Pureservice som ikke lenger eksisterer og oppdaterer status på dem

## Systemkrav
- En instans av Jamf Pro
- En Pureservice-instans
- Pureservice Assets satt opp med to typer: Datamaskin og mobilenhet
- PHP 8.x og PHP composer på maskinen som skal utføre synkroniseringen

## Manuell Installasjon
1. Last ned eller klon jamf2pureservice
2. Kjør `composer install` for å installere biblioteker og rammeverk
3. Kopier .env.example til .env, og fyll ut nødvendige verdier for koblinger mot Jamf Pro og Pureservice
4. Kjør synkroniseringen med `php artisan jamf2pureservice:sync`

## Nødvendige .env-variabler

Det er en rekke variabler som er nødvendige for at skriptet skal få gjort alt som trengs. Mye av dette krever oppsett i Pureservice. Variablene kan settes i .env-fila, eller de kan settes opp som miljøvariabler før kjøring. Sistnevnte er å foretrekke om man bruker Pipelines el.l. for å kjøre synkroniseringen.

| Variabel | Eksempelverdi | Beskrivelse |
| ----------- | ----------- | ----------- |
| JAMFPRO_URL | https://customer.jamfcloud.com | Angir base-adressen til Jamf Pro-instansen. Det er ikke nødvendig å bruke /api el.l. |
| JAMFPRO_USER | let-me | Brukernavn for en bruker i Jamf Pro som har global lesetilgang |
| JAMFPRO_PASSWORD | passord | Passord til Jamf Pro-brukeren |
| PURESERVICE_URL | https://customer.pureservice.com | Bare-adressen til Pureservice-instansen |
| PURESERVICE_APIKEY | ey... | API-nøkkel til Pureservice |
| PURESERVICE_COMPUTER_ASSETTYPE_NAME | Device | Navnet til ressurstypen som brukes til datamaskiner |
| PURESERVICE_MOBILE_ASSETTYPE_NAME | Mobile | Navnet til ressurstypen som brukes til mobilenheter |

## License

This project is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
