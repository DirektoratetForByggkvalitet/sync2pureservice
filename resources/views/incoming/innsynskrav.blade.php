<h2>
    Innsynskrav for {{ count($dokumenter) }} journalpost{{ count($dokumenter) > 1 ? 'er' : '' }} fra sak {{ $sak['saksnr'] }}
    @if (isset($sak['saksnavn']))
        <br/>
        <strong>{{ $sak['saksnavn'] }}</strong>
    @endif
</h2>
<p>Bestillingsdato: {{ $bestilling['bestillingsdato'] }}</p>
<h4>Kravet gjelder følgende journalposter</h4>
<ul>
@foreach ($dokumenter as $dok)
    <li>
        <strong>Dokumentnr: {{ $dok['dokumentnr'] }}</strong><br/>
        Navn: {{ $dok['dokumentnavn'] }}<br/>
        Sekvensnr: {{ $dok['journalnr'] }}<br/>
        Saksbehandler: {{ $dok['saksbehandler'] }}
    </li>
@endforeach
</ul>
<h4>Bestilt av</h4>
<p>
    @if (isset($bestilling['kontaktinfo']['navn']) && $bestilling['kontaktinfo']['navn'] != '' && !is_array($bestilling['kontaktinfo']['navn']))
        <strong>{{ $bestilling['kontaktinfo']['navn'] }}</strong><br/>
    @endif
    @if (isset($bestilling['kontaktinfo']['organisasjon']) && $bestilling['kontaktinfo']['organisasjon'] != '' && !is_array($bestilling['kontaktinfo']['organisasjon']))
        {{ $bestilling['kontaktinfo']['organisasjon'] }}<br/>
    @endif
    E-postadresse: <strong>{{ $bestilling['kontaktinfo']['e-post'] }}</strong>
</p>

<p>eInnsyn-ID: {{ $bestilling['id'] }}</p>
