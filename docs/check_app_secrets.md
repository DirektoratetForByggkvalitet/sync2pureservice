## Check App Secrets ##

Denne funksjonen kan være veldig nyttig for de som har Azure og Pureservice.

Funksjonen henter inn informasjon om alle App Registrations (som brukes som Service Principals eller innloggingsmoduler for eksterne apper), og vil opprette sak i Pureservice dersom den finner passord eller sertifikater som utløper innen oppgitt tidsfrist (standard 30 dager).

Oppsettet krever at man har en egen App Registration allerede, som har tilgang til Applications.Read i Graph API. Denne må bruke sertifikat som innlogging, og man kan opprette et sertifikat med lang levetid for formålet.

Funksjonen er ment å kjøres en gang i uken med Github Actions. Dersom det allerede finnes en åpen sak som gjelder samme App Registration, vil funksjonen legge til et notat til den eksisterende saken. Det vil fungere som en påminnelse. Det samme vil skje om funksjonen finner at en annen hemmelighet eller sertifikat for samme App Registration har utløp.

### Miljøvariabler

| Variabel | Standardverdi | Kommentar |
|----|----|----|
| SECRET_CHECK_CATEGORIES | | Hvilke kategorier skal en ny sak få? Oppgis med punktum som skilletegn, f.eks. "Kategori 1.Kategori 2.Kategori 3". Verdien må korrespondere med kategori-treet i Pureservice. Du kan oppgi 0-3 kategorier på denne måten |
| SECRET_CHECK_ID_FIELD | customerReference | Feltet som skal brukes til å sette sakens referanse til App ID-en det gjelder. Det kan f.eks. være et custom field |
| SECRET_CHECK_REF_PREFIX | AppID_ | Prefix på referanseverdien. Funksjonen legger til App ID fra App Registration i tillegg |

