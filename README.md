# Jamf2Pureservice

** Dokumentasjonen er under oppretting… **

Dette er en noe unødvendig stor Laravel-installasjon ment for å synke [Jamf Pro](https://jamf.com) med [Pureservice Assets](https://pureservice.com).
## Hva gjør jamf2pureservice?

Ferdig installert vil jamf2pureservice tilby én kommandolinje-kommando som utfører følgende operasjoner:
1. Kobler til Jamf Pro og sjekker tilkoblingen
2. Kobler til Pureservice og setter opp koblingen mot ressurstyper og statuser basert på ressurstypenes navn
3. 
3. Går gjennom alle enheter fra Jamf Pro
    - Regner ut EOL og bestemmer status på enheter som skal fases ut
    - Oppdaterer/oppretter dem i Pureservice
    - Kobler enheter i Pureservice mot brukere, slik det er registrert i Jamf Pro
4. Ser etter enheter i Pureservice som ikke lenger eksisterer og oppdaterer status på dem

## Systemkrav
- En instans av Jamf Pro
- En Pureservice-instans med brukersynkronisering (f.eks. Azure AD) som er ajour med brukerne i Jamf Pro
- Pureservice Assets satt opp med to typer: Datamaskin og mobilenhet
- PHP 8.x og PHP composer på maskinen som skal utføre synkroniseringen
## Nødvendig oppsett i Pureservice

Før synkronisering kan kjøres må man definere de to ressurstypene Datamaskin og Mobilenhet i Pureservice. Du kan kalle ressurstypene hva du vil, og oppgi ressurstypenes navn som miljøvariabler.

### Felter for ressurstypene

Feltene er stort sett felles for de to ressurstypene, men feltnavnene kan også overstyres med miljøvariabler. Har lagt opp til at man kan ha forskjellige feltnavn for datamaskiner og mobilenheter, og når man oppgir miljøvariabler må [TYPE] i tabellen under erstattes med enten "COMPUTER" eller "MOBILE".

| Feltnavn | Type | Miljøvariabel | Beskrivelse |
| ----------- | ----------- | ----------- | ----------- |
| Navn | Navnefelt | PURESERVICE_[TYPE]_FIELD_NAME | Feltet som brukes som enhetens navn |
| Serienr | Unik verdi | PURESERVICE_[TYPE]_FIELD_SERIAL | Enhetens serienummer |
| Modell | Tekst | PURESERVICE_[TYPE]_FIELD_MODEL | Inneholder enhetens modellnavn |
| ModelID | Tekst | PURESERVICE_[TYPE]_FIELD_MODELID | Enhetens modell-ID, f.eks. 'MacMini11,1' |
| OS-versjon | Tekst | PURESERVICE_[TYPE]_FIELD_OS | Enhetens OS-versjon, merk at '-' må oversettes til '_45_' i APIet |
| Prosessor | Tekst | PURESERVICE_COMPUTER_FIELD_PROCESSOR | Enhetens prosessortype, brukes ikke av mobilenheter |
| Jamf-URL | Tekst med klikkbar lenke | PURESERVICE_[TYPE]_FIELD_JAMFURL | Lenke til enheten i Jamf Pro |
| Sist sett | Dato | PURESERVICE_[TYPE]_FIELD_LASTSEEN | Tidsangivelse for når enheten ble sist sett av Jamf Pro |
| Innmeldt | Dato | PURESERVICE_[TYPE]_FIELD_MEMBERSINCE | Tidsangivelse for når enheten første gang ble innrullert i Jamf Pro |
| EOL | Dato | PURESERVICE_[TYPE]_FIELD_EOL | Dato for når enheten forventes å skiftes ut. Regnes ut av jamf2pureservice |
| Kommentarer | Tekst | Brukes ikke | Tekstfelt for å skrive inn kommentarer for selve enheten. Brukes ikke av Pureservice |

Merk at enkelte tegn i feltnavnene må oversettes til koder i jamf2pureservice for å fungere med Pureservice sitt API. F.eks. må '-' erstattes med '_45_' og mellomrom (' ') må erstattes med '_32_'. Vi har lagt opp til at jamf2pureservice oversetter '-' og ' '. Kan være lurt å ikke bruke for mye spesialtegn i feltnavnene.

### Relasjoner

Vi har lagt opp til at jamf2pureservice kun vedlikeholder en relasjon mellom ressurs og tildelt bruker. Øvrige relasjoner blir ikke brukt i synkroniseringen. 

## Installasjon
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

## Nødvendige statusverdier

Vi har lagt opp til at systemet bruker en rekke statuser for å angi hvor i livsløpet en enhet befinner seg. Statusnavnene settes opp som miljøvariabler, og jamf2pureservice vil finne de IDer til de oppgitte statusene og lenke dem opp til enhetene.

## License

This project is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
