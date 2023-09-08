# Pureservice Utsendelse? #

Kort fortalt jobber denne funksjonen for å gjøre det mulig å sende ut masseutsendelser fra Pureservice.

Pureservice Utsendelse kan understøtte to forskjellige scenarier for masseutsendelser:

1. Utsendelse der vi sender en e-post til mange adressater uten å be om svar.
2. Utsendelse der vi ber om tilbakemelding fra adressatene, og oppretter én sak per adressat.

## Hovedfunksjonen ##

Pureservice Utsendelse er ment å kjøres jevnlig og har følgende hovedfunksjon:

- Finne masseutsendelser som er klare for utsendelse i Pureservice
- Tolke [mottakerlister](mailinglists.md) som er koblet til sakene
- (Scenario 1) Formatere og sende ut e-posten som er lagt inn som melding i saken til adressatene
- (Scenario 2) Opprette kopier av utsendelse-saken og med enkeltadressater som adressat og sende ut meldingene

## Opprette masseutsendelse i Pureservice ##

Det å lage en masseutsendelse vil være veldig likt det å opprette en vanlig sak, med et par ekstra steg.

1. Opprett en sak med en beskrivelse og tittel. Saken kobles til en sluttbruker som er opprettet for masseutsendelser.
2. Sett klassifisering slik den skal være (sakstype og kategorier)
3. Koble saken til mottakerlisten(e) som skal brukes for utsending.
4. Skriv den utgående meldingen som en selvopprettet kommunikasjonstype ("Til utsending"). Legg eventuelle vedlegg til i saken.


## Oppsett i Pureservice ##

For at en sak skal behandles som en masseutsendelse trenger man i utgangspunktet bare å opprette to fiktive sluttbrukere i Pureservice, én for enveis utsendelse (scenario 1), og én for utsendelser som skal opprette saker (scenario 2). E-postadressen til disse brukerne bør være en adresse som ikke finnes, f.eks. 'masseutsendelse-ikke-svar@pureservice.local' og 'masseutsendelse-med-svar@pureservice.local'. Disse e-postadressene må også oppgis i miljøvariablene til Pureservice Utsendelse som vist under.

### Mottakerlister ###
Mottakerlistene til utsendelsene må opprettes som Assets i Pureservice. Her er et raskt oppsett:

1. Opprett en ressurstype, kall den f.eks. "Mottakerliste". Den trenger ikke noen felter annet enn navn og unikt navn, men vi legger på et felt der vi kan spesifisere en saksbehandler som listeanvarlig.
2. Under ressurstypens relasjoner må man sette navn på relasjonene som også legges inn i miljøvariablene

[Mer om dette her](mailingliste.md)
