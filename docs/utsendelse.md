# Pureservice Utsendelse #

Kort fortalt jobber denne funksjonen for å gjøre det mulig å sende ut masseutsendelser fra Pureservice.

Pureservice Utsendelse kan understøtte to forskjellige scenarier for masseutsendelser:

1. Utsendelse der vi sender en e-post til mange adressater uten å be om direkte svar.
2. Utsendelse der vi ber om tilbakemelding fra adressatene, og oppretter én sak per adressat.

*For øyeblikket er det kun masseutsending med scenario 1 som fungerer.*

## Hovedfunksjonen ##

Pureservice Utsendelse er ment å kjøres jevnlig og har følgende hovedfunksjon:

- Finne masseutsendelser som er er sendt ut fra Pureservice
- Tolke [mottakerlister](mailinglists.md) som er koblet til sakene
- **Scenario 1:** Formatere og sende ut e-posten som er lagt inn som melding i saken til adressatene
- **Scenario 2:** Opprette kopier av utsendelse-saken og med enkeltadressater som adressat og sende ut meldingene
- Kvittere ut og løse den opprinnelige saken

### Sekundær funksjon

Innkommende meldinger fra eFormidling fører til at det blir opprettet en eFormidling-sluttbruker for virksomheten som er avsender. Denne sluttbrukeren har typisk en e-postadresse som ender med 'pureservice.local' (kan endres, se nedenfor). 

Hvis saksbehandler svarer på en slik sak vil meldingen ende opp som ulevert i Pureservice, fordi e-post ikke kan leveres til en slik fiktiv adresse. Den sekundære funksjonen til Pureservice Utsending blir derfor å ta tak i slike uleverte meldinger og sende dem ut via eFormidling.

## Oppsett i Pureservice ##

For at en sak skal behandles som en masseutsendelse trenger man i utgangspunktet bare å opprette noen fiktive sluttbrukere i Pureservice og noen statuser.

Sluttbrukerne brukes til å angi om sync2pureservice skal foretrekke e-post eller eFormidling som kanal for utsending av innholdet.

| Variabel | Standardverdi | Kommentar |
|----|----|----|
| PURESERVICE_EF_DOMAIN | pureservice.local | Domene som brukes til å identifisere eFormidling-sluttbrukere, f.eks. '[orgnr]@eformidling.pureservice.local' |
| PURESERVICE_DISPATCH_EMAIL | ut@e-post.pureservice.local | Sluttbruker som angir at forsendelsen skal ut med e-post |
| PURESERVICE_DISPATCH_EF | ut@eformidling.pureservice.local | Sluttbruker som brukes til å angi masseutsending med eFormidling som medium |
| PURESERVICE_DISPATCH_SOLVED_STATUS | Løst | Status som settes på saken når utsendingen er ferdig |
| PURESERVICE_TICKET_STATUS_SENT | Venter - sluttbruker | Status som brukes for å finne saker med utgående meldinger |

Miljøvariablene over kommer i tillegg til [de som brukes for selve mottakerlistene](mailingliste.md).

### Mottakerlister ###
Mottakerlistene til utsendelsene må opprettes som Assets i Pureservice. Her er et raskt oppsett:

1. Opprett en ressurstype, kall den f.eks. "Mottakerliste". Den trenger ikke noen felter annet enn navn og unikt navn, men vi legger på et felt der vi kan spesifisere en saksbehandler som listeanvarlig.
2. Under ressurstypens relasjoner må man sette navn på relasjonene som også legges inn i miljøvariablene

[Mer om dette her](mailingliste.md)

## Oppsett for utsendelse av e-post fra sync2pureservice ##

Laravel støtter flere mulige oppsett for utsending av e-post. [Mer om dette her](https://laravel.com/docs/10.x/mail).

Sync2pureservice er satt opp med e-postmetoden 'failover', der vi prioriterer 'microsoft-graph', og faller tilbake på 'smtp' hvis SMTP feiler. Dette kan endres/byttes ved å endre på MAIL_MAILER eller på rekkefølgen i [config/mail.php](../config/mail.php.

| Variabel | Standardverdi | Kommentar |
|----|----|----|
| MAIL_MAILER | failover | Velger rammeverk for e-postutsending. Vi bruker failover-metoden angitt i [config/mail.php](../config/mail.php) som standard. |
| MAIL_HOST | | E-posttjener for utsending gjennom SMTP-tjener |
| MAIL_PORT | 587 | Port for SMTP |
| MAIL_ENCRYPTION | tls | Krypteringsinnstilling for SMTP, anbefalt |
| MAIL_USERNAME | | Brukernavn for SMTP-autentisering |
| MAIL_PASSWORD | | Passord for SMTP-autentisering |
| MAIL_EHLO_DOMAIN | sync2pureservice.pureservice.com | Hva sync2pureservice kaller seg når den sender e-post til SMTP-tjeneren |
| MAIL_FROM_ADDRESS | noreply@pureservice.local | Avsender-adressen som brukes for utsending |
| MAIL_FROM_NAME | sync2pureservice | Navnet som brukes som avsender. Husk å bruke single-quotes (') hvis navnet inneholder mellomrom |
| MAIL_REPLYTO_ADDRESS | post@dibk.no | Reply-to-adressen som brukes. Dette skal fungere slik at mottakeren kan klikke på Svar-knappen og sende svaret til denne adressen i stedet for MAIL_FROM_ADDRESS |
| MICROSOFT_GRAPH_CLIENT_ID | | App-ID for en Microsoft Graph-klient definert i Azure AD |
| MICROSOFT_GRAPH_CLIENT_SECRET | | Hemmelighet (passord) for Microsoft Graph-klienten |
| MICROSOFT_GRAPH_TENANT_ID | | Tenant-ID for Azure AD |

## Utseende på utsendingene ##

For å bestemme hvordan utsendinger og sluttrapport ser ut bruker vi to Laravel Blade-maler. Disse befinner seg under resources/views 
- **rawmessage.blade.php**: Denne trenger ikke å endres på, da den bare inkluderer den ferdig formaterte meldingen fra Pureservice. Utseende på de utgående meldingene endrer man ved å endre på malene i Pureservice. 
- **report.blade.php**: Dette er malen for sluttrapporten. Her kan man endre på tekst og formatering som man ønsker.


## Opprette masseutsendelse i Pureservice ##

Det å lage og utføre en masseutsendelse vil være veldig likt det å opprette en vanlig sak, med et par ekstra steg.

1. Opprett en sak med en beskrivelse og tittel. Saken kobles til en sluttbruker som angir forsendelsesmåte (se under)
2. Sett klassifisering slik den skal være (sakstype og kategorier)
3. Koble saken til mottakerlisten(e) som skal brukes som mottakere
4. Opprett en ny melding i saken som er selve utsendingen. Legg til eventuelle vedlegg, og send meldingen.
5. Meldingen vil etter hvert bli markert med en trekant i Pureservice, og står som 'ikke levert'.

Det siste punktet er det som utløser at Pureservice Utsending kan finne utsendingen og levere den.
