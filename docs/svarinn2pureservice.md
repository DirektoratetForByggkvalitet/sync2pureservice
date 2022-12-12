# svarinn2pureservice: SvarUt-Mottak for Pureservice

Vi har bygd funksjonalitet for at jamf2pureservice kan fungere som SvarUt-mottak for virksomheten, ved at den sjekker og henter ned forsendelser fra SvarUt, og legger dem inn som saker i Pureservice. Vi kaller denne funksjonen "svarinn2pureservice".

## Systemkrav

- PHP 8.x med Composer installert
- En instans av Pureservice, med API-nøkkel satt opp
- Java JRE/JDK 11 eller nyere installert (for dekryptering)
- En kopi av [svarut-dekrypter](https://github.com/DirektoratetForByggkvalitet/svarut-dekrypter.git) installert
- Tilgang til å bruke [SvarUt](https://www.ks.no/svarut/), noe som inkluderer brukernavn, passord og privatnøkkel i PEM-format

## Hva gjør svarinn2pureservice?

svarinn2pureservice bruker REST-tjenesten [MottakService v1](https://developers.fiks.ks.no/svarut/integrasjon/mottaksservice-rest/) til å se etter innkommende forsendelser i kø.

Dersom det er forsendelser i køen vil svarinn2pureservice gjøre følgende:

- Opprette avsender som Firma og Sluttbruker i Pureservice hvis de ikke finnes fra før
- Laste ned forsendelsen(e), dekryptere og pakke ut filene
- Opprette en sak på forsendelsen i Pureservice, med informasjon fra forsendelsen i beskrivelse
- Laste opp filene som vedlegg til saken
- Merke forsendelsen(e) som mottatt i SvarUt

Saken vil bli fordelt til samhandlingssone og team oppgitt i miljøvariablene. Det kan være hensiktsmessig at dette er en fordelingssone, siden forsendelser ofte trenger å fordeles på tvers.

## Oppsett

1. Last ned eller klon jamf2pureservice
2. Kjør `composer install` for å installere biblioteker og rammeverk
3. Kopier fila .env.example til .env (`cp .env.example .env`), og fyll ut nødvendige miljøvariabler (se under) for koblinger mot SvarUt og Pureservice.
4. Kjør `php artisan key:generate` for å opprette en unik APP_KEY i .env
5. Kjør `php artisan svarinn2pureservice:run` for å kjøre 

## Utfordringer med e-postadresser

Forsendelser i SvarUt inneholder ikke e-postadresser, noe som Pureservice ikke liker så godt. Derfor har vi lagt til muligheten til å ha en Excel-fil som inneholder kommuner med e-postadresser. Filformatet er basert på det man får abonnert på fra kommuneregisteret.no, med følgende felter:

- A: Kommunenr
- B: Kommunenavn
- C: Postadresse
- D: Postnr
- E: Poststed
- F: E-postadresse

Av disse bruker vi B og F i løsningen per nå. Første linje i fila blir ignorert, bruk den til overskrifter. Man **kan** vedlikeholde en slik fil manuelt, det avhenger litt av mengden forskjellige avsendere man forventer. Det vil uansett oppstå tilfeller der man får forsendelser fra virksomheter som ikke er oppført i denne fila, og hvis man ikke har en slik fil vil det være vanlig. Løsningen vil da opprette avsender-sluttbrukeren med e-postadressen [orgnr].no_email@pureservice.local. Vi tenker at man som regel ikke trenger å svare på henvendelser fra SvarUt, men i tilfelle vil det være en enkelt for saksbehandler å korrigere brukerens e-postadresse i Pureservice før man sender svar.

## Miljøvariabler

Variabler i **fet skrift** har ingen standardverdi og må settes før kjøring. SVARINN_PS-verdiene som er *uthevet* må samkjøres med Pureservice-instansen.

| Variabel | Standardverdi | Beskrivelse |
| ----------- | ----------- | ----------- |
| **PURESERVICE_URL** | https://customer.pureservice.com | Base-adressen til Pureservice-instansen |
| **PURESERVICE_APIKEY** | ey... | API-nøkkel til Pureservice |
| *SVARINN_PS_SOURCE* | SvarUt | Navnet til kilden i Pureservice som skal brukes for SvarUt-forsendelser |
| *SVARINN_PS_TICKET_TYPE* | Henvendelse | Navn på sakstypen som skal brukes i Pureservice |
| *SVARINN_PS_ZONE* | Dispatchers | Samhandlingssone-navn som skal brukes for SvarUt-forsendelser |
| *SVARINN_PS_TEAM* | Dispatcher | Team-navn som skal brukes for SvarUt-forsendelser |
| *SVARINN_PS_PRIORITY* | Normal | Navn på prioriteten som skal settes på saken i Pureservice. Må finnes i Pureservice fra før av |
| *SVARINN_PS_STATUS* | Ny | Navn på statusen som skal settes på saken i Pureservice. Må finnes i Pureservice fra før av. |
| **SVARINN_USER** | | Brukernavn for innlogging til SvarUt MottakService |
| **SVARINN_SECRET** | | Passord for innlogging til SvarUt MottakService |
| SVARINN_PRIVATEKEY_PATH | storage/keys/privatekey.pem | Sti til privat nøkkel for dekryptering av forsendelsesfil |
| SVARINN_MAX_RETRIES | 3 | Hvor mange ganger vi skal prøve forespørsler på nytt før vi gir opp |
| SVARINN_TEMP_PATH | storage/svarinn_tmp | Mappe for utpakking av zip-filer |
| SVARINN_DOWNLOAD_PATH | storage/svarinn_download | Mappe for nedlasting av forsendelsesfil |
| SVARINN_DEKRYPT_PATH | storage/svarinn_dekryptert | Mappe der dekryptert fil havner |
| SVARINN_PS_VISIBILITY | 2 | Setter synlighet for sluttbruker på saken som blir opprettet. Standard (2) er å sette saken "Ikke synlig" |
| SVARINN_PS_USER_ROLE_ID | 10 | Rolle-ID for brukeren som blir opprettet fra forsendelsen. Standard er sluttbruker-rollen |
| SVARINN_PS_REQUEST_TYPE | Ticket | RequestType for forespørselen. Dette er normalt ikke noe man trenger å endre fra standard |
| SVARINN_EXCEL_LOOKUP_FILE | false | Excel-fil lastet ned fra kommuneregisteret.no (inneholder kommunenavn i kolonne B og e-postadresse i kolonne F), lagret under storage (storage/{SVARINN_EXCEL_LOOKUP_FILE}). Sett til false for å slå av funksjonaliteten. |
| DEKRYPTER_VER | 1.0 | Versjonsnummer for dekrypter |
| DEKRYPTER_JAR | dekrypter-{DEKRYPTER_VER}/dekrypter-{DEKRYPTER_VER}.jar | Sti til dekrypter.jar. |
| SVARINN_DRYRUN | false | Hvis satt til true vil svarinn2pureservice laste ned forsendelser og opprette saker i Pureservice, men vil ikke merke forsendelser som mottatt eller feilet hos SvarUt. Du kan også oppgi et filnavn til en json-fil med [eksempeldata](https://developers.fiks.ks.no/svarut/integrasjon/mottaksservice-rest/) her, men den må i tilfelle inneholde 'downloadUrl' som peker til nedlastbare filer. JSON-fila skal ligge under storage i filstrukturen (storage/{SVARINN_DRYRUN}). Dette er ment å gjøre det enklere og teste funksjonaliteten før driftsetting. |

