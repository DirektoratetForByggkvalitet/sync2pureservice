@php
    $dokumenter = $bestilling['dokumenter']->where('saksnr', $saksnr);
@endphp
<h2>
    Innsynskrav for {{ count($dokumenter) }} journalpost fra sak {{ $saksnr }}
    @if (isset($saksnavn))
        <br />{{ $saksnavn }}
    @endif
</h2>
<p>Bestillingsdato: {{ $bestilling['bestillingsdato'] }}</p>
<h4>Innsynskravet er bestilt av</h4>
<p>
    @if ($bestilling['kontaktinfo']['navn'] != '')
        {{ $bestilling['kontaktinfo']['navn'] }}<br/>
    @endif
    @if ($bestilling['kontaktinfo']['organisasjon'] != '')
        {{ $bestilling['kontaktinfo']['organisasjon'] }}<br/>
    @endif
    E-postadresse: {{ $bestilling['kontaktinfo']['e-post'] }}
</p>
<h4>Journalposter</h4>
@foreach ($dokumenter as $dok)
    <p>
        Dokumenttnr: {{ $dok['dokumentnr'] }}<br/>
        Sekvensnr: {{ $dok['journalnr'] }}<br/>
        Saksbehandler: {{ $dok['saksbehandler'] }}
    </p>
@endforeach

<p>eInnsyn-ID: {{ $bestilling['id'] }}</p>
<p>PS! Sakens navn og navn pÃ¥ journalposter er tilgjengelig, da disse ikke oppgis i bestillingen</p>
