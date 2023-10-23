# CleanUpUsers

Kommanoden `./artisan pureservice:clean-up` kan nå brukes. Konsoll-kommandoen utfører klassen CleanUpUsers, som er laget for å håndtere brukerinformasjon som kan være til fare for brukerens privatliv.

Når en person sender innsynskrav eller e-post inn til Pureservice er det ikke alltid at e-postinformasjonen inneholder navn, og da kan det hende at det opprettes en sluttbruker i Pureservice som ikke har fornavn og etternavn, eller at den har e-postadressen som fornavn og etternavn. Det er ikke ønskelig. Vi prøver å unngå at sluttbrukers e-postadresse kommer ut på eInnsyn, og har dermed laget CleanUpUsers for å forhindre det.

Til å begynne med kobler kommandoen seg opp mot Pureservice og laster ned info om alle sluttbrukere. Dette krever selvsagt at sync2pureservice er satt opp med URL og API-token, som beskrevet andre steder.

Deretter går kommandoen gjennom alle sluttbrukere og ser etter åpenbare feil i informasjonen:

- Tomme fornavn og/eller etternavn
- E-postadresser i fornavn og/eller etternavn
- Reverserte navn (Hansen, Per)

I de to første tilfellene vil kommandoen bruke e-postadressen til å lage et fornavn. Vi vil se etter naturlige skilletegn i e-postadressen som angir fornavn og etternavn. Dersom f.eks. e-postadressen er 'jan.olsen@e-post.com' vil brukerens navn bli Jan Olsen.

Hvis kommandoen ikke finner noen logikk i e-postadressen (f.eks. 'jb@norge.no') vil brukeren få fornavn som det som står foran '@' i adressen, og etternavnet '-'.

I det siste tilfellet vil kommandoen snu på navnet, slik at "Hansen, Per" blir til "Per Hansen".

## Automatisk firmakobling

Kommandoen vil også se på domeneinformasjonen i e-postadressen (det etter '@') og vil forsøke å matche det mot et firma registrert i Pureservice. Dersom vi i Pureservice har et firma med e-postadressen 'post@firma.no' og får en e-post fra 'jan@firma.no', vil denne sluttbrukeren bli koblet mot firmaet.

Det vil kunne oppstå problemer med slike koblinger. [Pureservice-innstillingene](config/pureservice.php) til sync2pureservice har fått et tillegg, 'domainmapping', der man kan tilpasse koblinger som ikke fungerer som de skal. For eksempel har vi lagt inn at domenet 'gmail.com' ikke skal peke mot noe firma:
`        [
            'domain' => 'gmail.com',
            'company' => false,
        ],
`

Vi har også lagt til 'outlook.com', 'epost.no', 'online.no' og noen andre ISP-domener som typisk ikke vil fungere som match mot et firma i Pureservice.

Dersom man skal ha en spesifikk mapping for et domene legger man det til ved å skrive inn foretakets navn i Pureservice som verdi for 'company'. Eksempel for Bergen kommune: 
`[
    'domain' => 'bergen.kommune.no',
    'company' => 'Bergen Kommune',
],`

Hvis det oppstår problemer med matchingen kan det ofte være lurt å deaktivere mapping for det aktuelle domenet ved å legge det til i domainmapping-oppsettet.
