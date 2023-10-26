# Pureservice Utsendelse #

Kort fortalt jobber denne funksjonen for å gjøre det mulig å sende ut masseutsendelser fra Pureservice.

Pureservice Utsendelse kan understøtte to forskjellige scenarier for masseutsendelser:

1. Utsendelse der vi sender en e-post til mange adressater uten å be om direkte svar.
2. Utsendelse der vi ber om tilbakemelding fra adressatene, og oppretter én sak per adressat.

*For øyeblikket er det kun masseutsending med scenario 1 som fungerer.*

## Hovedfunksjonen ##

Pureservice Utsendelse er ment å kjøres jevnlig og har følgende hovedfunksjon:

- Finne masseutsendelser som er klare for utsendelse i Pureservice
- Tolke [mottakerlister](mailinglists.md) som er koblet til sakene
- **Scenario 1:** Formatere og sende ut e-posten som er lagt inn som melding i saken til adressatene
- **Scenario 2:** Opprette kopier av utsendelse-saken og med enkeltadressater som adressat og sende ut meldingene
- Kvittere ut og løse den opprinnelige saken

### Sekundær funksjon

Det å kunne svare på innkommende eFormidling-henvendelser er et scenario som kommer inn litt fra siden, og vi tror det kan være viktig. 

Vi tenker å opprette en funksjon som kan hente opp saker med sluttbrukere som har eFormidling-adresser (orgnr@eformidling.pureservice.local) og sende svaret ut som eFormidling-melding.

## Opprette masseutsendelse i Pureservice ##

Det å lage en masseutsendelse vil være veldig likt det å opprette en vanlig sak, med et par ekstra steg.

1. Opprett en sak med en beskrivelse og tittel. Saken kobles til en sluttbruker som angir forsendelsesmåte (se under)
2. Sett klassifisering slik den skal være (sakstype og kategorier)
3. Koble saken til mottakerlisten(e) som skal brukes som mottakere
4. Skriv den utgående meldingen som en selvopprettet kommunikasjonstype ("Til utsending"). Legg eventuelle vedlegg til i saken
5. Sett status for masseutsendelse etter ønsket metode (scenario 1 eller 2)


## Oppsett i Pureservice ##

For at en sak skal behandles som en masseutsendelse trenger man i utgangspunktet bare å opprette noen fiktive sluttbrukere i Pureservice, en kommunikasjonstype og noen statuser.

Sluttbrukerne brukes til å angi om sync2pureservice skal foretrekke e-post eller eFormidling som kanal for utsending av innholdet. *Utsending vie eFormidling er fortsatt under utvikling, og kan ikke brukes enda.*

Ved enveis utsendelse kan man også velge mellom utsending via e-post eller gjennom eFormidling. Dette er dog ikke klart enda.

| Variabel | Standardverdi | Kommentar |
|----|----|----|
| PURESERVICE_DISPATCH_EMAIL | ut@e-post.pureservice.local | Sluttbruker som angir at forsendelsen skal ut med e-post |
| PURESERVICE_DISPATCH_EF | ut@eformidling.pureservice.local | Sluttbruker som brukes til å angi masseutsending med eFormidling som medium |
| PURESERVICE_DISPATCH_COMMTYPE_NAME | Utsendelse - innhold | Navnet på kommunikasjonstypen som skal brukes til utsendelsesteksten |
| PURESERVICE_DISPATCH_STATUS | Til ekspedering | Navnet på statusen som skal settes på saken for å utløse utsending (**scenario 1**) |
| PURESERVICE_121_STATUS | Klar til splitt | Navnet på statusen som skal settes på saken for å utløse oppretting av nye saker (**scenario 2**) |
| PURESERVICE_DISPATCH_SOLVED_STATUS | Løst | Status som settes på saken når utsendingen er ferdig |

Miljøvariablene over kommer i tillegg til [de som brukes for selve mottakerlistene](mailingliste.md).

### Mottakerlister ###
Mottakerlistene til utsendelsene må opprettes som Assets i Pureservice. Her er et raskt oppsett:

1. Opprett en ressurstype, kall den f.eks. "Mottakerliste". Den trenger ikke noen felter annet enn navn og unikt navn, men vi legger på et felt der vi kan spesifisere en saksbehandler som listeanvarlig.
2. Under ressurstypens relasjoner må man sette navn på relasjonene som også legges inn i miljøvariablene

[Mer om dette her](mailingliste.md)

## Oppsett for utsendelse av e-post fra sync2pureservice ##

Laravel støtter flere mulige oppsett for utsending av e-post. [Mer om dette her](https://laravel.com/docs/10.x/mail).

Sync2pureservice er satt opp med e-postmetoden 'failover', der vi prioriterer 'smtp', og faller tilbake på 'microsoft-graph' hvis SMTP feiler. Dette kan endres ved å endre på MAIL_MAILER.

| Variabel | Standardverdi | Kommentar |
|----|----|----|
| MAIL_MAILER | failover | Velger rammeverk for e-postutsending. Vi bruker failover-metoden angitt i [config/mail.php](../config/mail.php) som standard. |
| MAIL_HOST | | E-posttjener for utsending gjennom SMTP-tjener |
| MAIL_PORT | 587 | Port for SMTP |
| MAIL_ENCRYPTION | tls | Krypteringsinnstilling for SMTP |
| MAIL_USERNAME | | Brukernavn for SMTP-autentisering |
| MAIL_PASSWORD | | Passord for SMTP-autentisering |
| MAIL_EHLO_DOMAIN | f.eks. sync2pureservice.dibk.cloud | Hva sync2pureservice kaller seg når den sender e-post til SMTP-tjeneren |
| MAIL_FROM_ADDRESS | f.eks. ikkesvar@dibk.no | Avsender-adressen som brukes for utsending |
| MAIL_FROM_NAME | f.eks. 'Direktoratet for byggkvalitet' | Navnet som brukes som avsender. Husk å bruke ' når navnet inneholder mellomrom, som vist i eksemplet |
| MAIL_REPLYTO_ADDRESS | post@dibk.no | Reply-to-adressen som brukes. Dette skal fungere slik at mottakeren kan klikke på Svar-knappen og sende svaret til denne adressen i stedet for MAIL_FROM_ADDRESS |
| MICROSOFT_GRAPH_CLIENT_ID | | App-ID for en Microsoft Graph-klient definert i Azure AD |
| MICROSOFT_GRAPH_CLIENT_SECRET | | Hemmelighet (passord) for Microsoft Graph-klienten |
| MICROSOFT_GRAPH_TENANT_ID | | Tenant-ID for Azure AD |
