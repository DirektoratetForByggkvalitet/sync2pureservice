@php
    $dokumenter = $bestilling['dokumenter']->where('saksnr', $saksnr);
@endphp
<h2>
    Innsynskrav for {{ count($dokumenter) }} journalpost{{ count($dokumenter) > 1 ? 'er' : '' }} fra sak {{ $saksnr }}
    @if (isset($saksnavn))
        <br />{{ $saksnavn }}
    @endif
</h2>
<p>Bestillingsdato: {{ $bestilling['bestillingsdato'] }}</p>
<h4>Kravet gjelder følgende journalposter</h4>
<ul>
@foreach ($dokumenter as $dok)
    <li>
        <strong>Dokumentnr: {{ $dok['dokumentnr'] }}</strong><br/>
        Sekvensnr: {{ $dok['journalnr'] }}<br/>
        Saksbehandler: {{ $dok['saksbehandler'] }}
    </li>
@endforeach
</ul>
<h4>Bestilt av</h4>
<p>
    @if ($bestilling['kontaktinfo']['navn'] != '')
        <strong>{{ $bestilling['kontaktinfo']['navn'] }}</strong><br/>
    @endif
    @if ($bestilling['kontaktinfo']['organisasjon'] != '')
        {{ $bestilling['kontaktinfo']['organisasjon'] }}<br/>
    @endif
    E-postadresse: <strong>{{ $bestilling['kontaktinfo']['e-post'] }}</strong>
</p>

<p>eInnsyn-ID: {{ $bestilling['id'] }}</p>
<p>PS! Navn på sak og journalposter er ikke tilgjengelige, da disse ikke er oppgitt i innsynskravet</p>
