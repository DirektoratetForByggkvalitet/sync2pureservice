# Import av offentlige etater #

Vi har laget en funksjon (`./artisan pureservice:offentlige2ps`) som lar oss importere virksomheter og brukere fra BRREG inn i Pureservice.

Funksjonen bruker oppsett fra config/enhetsregisteret.php til å søke opp følgende:

- Alle kommuner
- Alle fylkeskommuner
- Alle statlige departementer
- Alle underliggende virksomheter til departementene

Den mellomlagrer virksomheten i en intern database. For å gi praktisk nytte i Pureservice opprettes det også brukere for virksomhetene i samme database:

- eFormidling-bruker basert på virksomhetens organisasjonsnummer
- Postmottak-bruker for virksomheten, basert på en Excel-fil med mapping mellom virksomhet og e-postadresse.

Med databasen befolket kjøres en jobb mot Pureservice sitt API, der skriptet oppretter virksomheter og brukere. Dersom virksomhet og/eller bruker finnes i Pureservice fra før av blir dataene oppdaterte.

## Oppsett ##

For at funksjonen skal kunne kjøre innenfor PHP sine minnebegrensninger bruker denne funksjonen databasen SQLite til å mellomlagre dataene før de lastes opp til Pureservice. Bruk `./artisan migrate:fresh` for å nullstille og klargjøre databasen (som ligger i database/database.db) før den tas i bruk.

Den medfølgende Excel-filen [storage/virksomheter.xlsx](../storage/virksomheter.xlsx) brukes til å mappe mellom virksomhetens orgnr og e-postadresse. Fila inneholder per nå alle kommuner, fylkeskommuner, samt statlige virksomheter og underliggende statlige virksomheter, oppdatert for 01.01.2024. Foruten orgnr og e-post brukes ikke det øvrige innholdet i fila, det hentes direkte fra BRREG.

### Egendefinerte felter i Pureservice ###

I Pureservice kan det være lurt å opprette egendefinerte felter for firma og bruker, slik at man kan lagre firmaet sin kategori, samt sette et ikkesvar-felt til '1'. 

Dette siste er et triks som vi bruker for å unngå e-postloop, spesielt med offentlige postmottak, der det sendes autosvar i loop mellom partene. Vi har slått av Pureservice sin standardregel for å sende e-post til sluttbruker når en sak opprettes, og erstattet den med en egen regel som egentlig gjør det samme, men kun dersom ikkesvar-feltet er tomt. På den måten kan vi stoppe en e-postloop ved å gå inn på sluttbrukeren og sette ikkesvar til 1. Derfor importerer vi også alle SvarUt- og Postmottak-brukere med dette feltet satt til 1.

Dersom du vil bruke de egendefinerte feltene må du legge til IDen for de respektive feltene (f.eks. 'cf_2') i miljøvariablene før skriptet kjøres. IDen vises i Pureservice når du ser på listen over egendefinerte felt for bruker og/eller firma.

### Miljøvariabler ###

Som vanlig må man ha en rekke miljøvariabler i .env for å bruke denne delen, også:

| Variabel | Standardverdi | Beskrivelse |
| ----------- | ----------- | ----------- |
| PURESERVICE_COMPANY_CATEGORY_FIELD | false | ID til det egendefinerte feltet (av typen tekst) i Pureservice som brukes til å lagre firmaets kategori |
| PURESERVICE_USER_NOEMAIL_FIELD | false | ID til det egendefinerte feltet (av typen tall) i Pureservice som brukes til å sette en verdi som unngår e-postloop  |

## Normal bruk ##

```
./artisan migrate:fresh
./artisan pureservice:offentlige2ps
```

