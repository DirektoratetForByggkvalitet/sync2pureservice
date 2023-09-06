# Funksjonalitet mot eFormidling #

Ideelt sett burde vi kunne bruke Pureservice til å sende og motta meldinger gjennom eFormidling. Dessverre er støtten for eFormidling veldig bundet til tradisjonell sak/arkiv, men det er mulig å komme rundt.

For å kunne bruke eFormidling har vi valgt å etablere et integrasjonspunkt som en Azure App Service. Dette er i praksis en docker-konteiner som kjører bak en webtjener. Vi har også satt denne opp med IP whitelisting og basic auth for å gjøre den litt sikrere i bruk.

**Integrasjonspunktet til DiBK er aktivert for følgende tjenester**

* DPO - Altinn digital postkasse for offentlige (statlige) virksomheter
* DPV - Digital postkasse for virksomheter
* DPE - eInnsyn (publisering og innsynskrav)
* DPF - SvarUt sending og mottakstjeneste

Integrasjonspunktet vil automatisk ta imot innkommende meldinger og lagre dem i sin database, slik at de er klare for henting gjennom [eFormidling 2-APIet](https://docs.digdir.no/docs/eFormidling/Utvikling/integrasjonspunkt_eformidling2_api). Utgående meldinger tas imot med samme API, og sendes umiddelbart videre til eFormidling sin backend for videre levering.

## Innkommende meldinger ##

Funksjonaliteten rundt innkommende eFormidling-meldinger er basert på én tjeneste i sync2pureservice: **eformidling:inn**. Denne tjenesten logger seg på integrasjonspunktet og ser etter nye meldinger.

En eFormidlings-melding er i all hovedsak basert på metadata i JSON- og XML-format, samt vedlegg. Vedleggene er det reelle innholdet i forsendelsen, resten er 'konvolutten'.

Når en forsendelse blir funnet på integrasjonspunktet vil sync2pureservice laste ned vedleggene og bruke metadataene til å opprette avsenders virksomhet og en egen eFormidling-avsender i Pureservice. Deretter oppretter sync2pureservice en sak der metadataene brukes til å lage en beskrivelse, og laster opp vedleggene til saken. Saken fordeles til valgt sone og team, og er klar for behandling i Pureservice.
## Utgående meldinger ##

Vi arbeider aktivt for å etablere en metodikk for sending av meldinger fra Pureservice til eFormidling, men dette er noe som vil ta litt tid å få på plass. Dette vil bli inkludert som en del av [masseutsending-funksjonen](utsendelse.md).

# Oppsett #

Det er en rekke miljøvariabler som må settes for at eFormidling skal fungere i sync2pureservice. Noen av disse har fallbacks, og vil falle tilbake til andre miljøvariabler eller standardverdier dersom de ikke er satt.

| Variabel | Standardverdi | Kommentar |
|----|----|----|
| DPE_AUDIT_TYPE | Innsynskrav | Sakstypen som skal brukes for innsynskrav |
| DPE_TEAM_NAME | Postmottak | Navn på Pureservice-teamet som skal få innsynskrav |

(WIP)
