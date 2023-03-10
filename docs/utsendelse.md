# Hva gjør Pureservice Utsendelse? #

Kort fortalt jobber denne funksjonen for å gjøre det mulig å sende ut masseutsendelser fra Pureservice.

Pureservice Utsendelse kan understøtte to forskjellige scenarier for masseutsendelser:

1. Utsendelse der vi sender en e-post til mange adressater uten å be om svar.
2. Utsendelse der vi ber om tilbakemelding fra adressatene, og oppretter én sak per adressat.

## Hovedfunksjonen ##

Pureservice Utsendelse er ment å kjøres jevnlig og har følgende hovedfunksjon:

- Finne masseutsendelser som er klare for utsendelse i Pureservice
- Hente inn mottakerlister som er koblet til sakene
- (Scenario 1) Formatere og sende ut e-posten som er lagt inn som melding i saken til adressatene
- (Scenario 2) Opprette kopier av utsendelse-saken og med enkeltadressater som adressat og sende ut meldingene

## Oppsett i Pureservice ##

For at en sak skal behandles som en masseutsendelse trenger man i utgangspunktet bare å opprette to fiktive sluttbrukere i Pureservice, én for enveis utsendelse (scenario 1), og én for utsendelser som skal opprette saker (scenario 2). E-postadressen til disse brukerne bør være en adresse som ikke finnes, f.eks. 'masseutsendelse-ikke-svar@pureservice.local' og 'masseutsendelse-med-svar@pureservice.local'. Disse e-postadressene må også oppgis i miljøvariablene til Pureservice Utsendelse som vist under.

### Adresselistene ###
Adresselistene til utsendelsene må opprettes som Assets i Pureservice. Her er et raskt oppsett:

Opprett en ressurstype, kall den f.eks. "Mottakerliste". 


## Opprette masseutsendelse i Pureservice ##

Det å lage en masseutsendelse vil være veldig likt det å opprette en vanlig sak, med et par ekstra steg.
