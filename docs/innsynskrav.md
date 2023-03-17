# Innsynskrav-funksjonalitet #

Funksjonaliteten rundt prosessering av innsynskrav stammer fra et behov for å etterligne oppførselen til Sak-/arkivsystemer når man mottar et innsynskrav fra eInnsyn.

eInnsyn tilgjengeliggjør innsynskrav som en XML-fil (order.xml) som inneholder metadata om innsynskravet:

- Avsenders info (minimum e-postadresse)
- Dokumentene det ønskes innsyn i

Ønsket er at dette skal oppstå som én ticket per dokument, med avsender som avsender. På den måten kan man i Pureservice fordele saken til saksansvarlig for akkurat det dokumentet, og opprettholde svarfrist for hvert dokument.

## Scenario 1: Innsynskrav kommer som e-post til Pureservice ##

Dersom man bruker einnsyn-klient-x.x.x.jar fra Digdir til å hente inn innsynskrav og sende dem på e-post til Pureservice må man lage en regel som slår til etter følgende kriterier:

1. Ny sak opprettes
2. Avsender har e-post ikke_svar@einnsyn.no

### Utfør følgende ###

1. Tildel saken til et team for prosessering (tips: privat team)
2. Sakstype settes til Innsynskrav (eller hva man vil bruke)
3. Send HTTP-kall til en tjeneste som kan kjøre `/artisan innsynskrav:splittEksisterende $SakId` der $SakId er sakens RequestNumber (ikke ID) i Pureservice. 

I dette tilfellet Bitbucket Pipelines:
- Metode: POST
- For hver Sak (standard)
- Url: https://api.bitbucket.org/2.0/repositories/dibk/sync2pureservice/pipelines/
- Header: Authorization = Bearer [repository-token] - token må ha lese-tilgang til repository og full tilgang til Pipelines
- Header: Accept = application/json
- Autentisering: ingen
- Innhold type: JSON
- JSON:

`{
  "target": {
    "type": "pipeline_ref_target",
    "ref_type": "branch",
    "ref_name": "master",
    "selector": {
      "type": "custom",
      "pattern": "splittInnsynskrav"
    }
  },
  "variables": [
    {
      "key": "SakID",
      "value": "{{Ticket.Current.RequestNumber}}"
    }
  ]
}`

Koden vil kjøre custom-pipeline **splittInnsynskrav** med miljøvariabelen SakID satt til sakens RequestNumber.

Dette scenariet er under utvikling, og vil 

## Scenario 2: Innsynskrav skal hentes fra integrasjonspunkt ##

I dette tilfellet vil vi jevnlig kjøre en Pipeline som sjekker etter innsynskrav i et integrasjonspunkt. Dersom det finnes innsynskrav vil de lastes ned og opprettes som enkeltsaker i Pureservice. Samme mønster som i scenario 1, men uten at saken allerede finnes i Pureservice. Det krever dog noen miljøvariabler som må stemme med Pureservice-instansen:

| Variabel | Standardverdi | Kommentar |
|----|----|----|
| DPE_AUDIT_TYPE | Innsynskrav | Sakstypen som skal brukes for innsynskrav |
| DPE_TEAM_NAME | Postmottak | Navn på Pureservice-teamet som skal få innsynskrav |

Kommandoen som kjøres i dette tilfellet vil være `./artisan innsynskrav:hent`

Denne funksjonaliteten er ikke foreløpig planlagt.

