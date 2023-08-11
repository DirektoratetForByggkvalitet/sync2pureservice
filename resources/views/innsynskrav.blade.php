
@php
    $dokumenter = $bestilling['dokumenter']->where('saksnr', $saksnr);
@endphp
@endphp<!DOCTYPE html>
<html>
<head>
    <meta name="description" content="Innsynskravmal v1.0.230811" />
    @include('parts.css')
    <title>{{ $subject }}</title>
</head>
<body>
<div class="box">
    <p>
        Innsynskrav for {{ count($dokumenter) }} fra sak {{ $saksnr }}<br />
        @if (isset($saksnavn))
            {{ $saksnavn }}
        @endif
    </p>
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
    <p>PS! Sakens navn og navn på journalposter er tilgjengelig, da disse ikke oppgis i bestillingen</p>
</div>
</body>
</html>
