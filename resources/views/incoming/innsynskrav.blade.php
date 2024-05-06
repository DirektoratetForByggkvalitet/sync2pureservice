@php
    $dokumenter = $bestilling['dokumenter']->where('saksnr', $saksnr);
    $saksData = $docMetadata->firstWhere('saksnr', $saksnr);
    $saksnavn = $saksData['saksnavn'];
@endphp
<h2>
    Innsynskrav for {{ count($dokumenter) }} journalpost{{ count($dokumenter) > 1 ? 'er' : '' }} fra sak {{ $saksnr }}
    @if (isset($saksnavn))
        <br />
        <strong>{{ $saksnavn }}</strong>
    @endif
</h2>
<p>Bestillingsdato: {{ $bestilling['bestillingsdato'] }}</p>
<h4>Kravet gjelder følgende journalposter</h4>
<ul>
@foreach ($dokumenter as $dok)
    @php
        $metadata = $docMetadata->firstWhere('sekvensnr', $dok['journalnr']);
        if (!isset($metadata['dokumentnavn'])):
            $metadata['dokumentnavn'] = 'Ikke tilgjengelig';
        endif;
    @endphp
    <li>
        <strong>Dokumentnr: {{ $dok['dokumentnr'] }}</strong><br/>
        Navn: {{ $metadata['dokumentnavn'] }}<br/>
        Sekvensnr: {{ $dok['journalnr'] }}<br/>
        Saksbehandler: {{ $dok['saksbehandler'] }}
    </li>
@endforeach
</ul>
<h4>Bestilt av</h4>
<p>
    @if ($bestilling['kontaktinfo']['navn'] != '' && !is_array($bestilling['kontaktinfo']['navn']))
        <strong>{{ $bestilling['kontaktinfo']['navn'] }}</strong><br/>
    @endif
    @if ($bestilling['kontaktinfo']['organisasjon'] != '' && !is_array($bestilling['kontaktinfo']['organisasjon']))
        {{ $bestilling['kontaktinfo']['organisasjon'] }}<br/>
    @endif
    E-postadresse: <strong>{{ $bestilling['kontaktinfo']['e-post'] }}</strong>
</p>

<p>eInnsyn-ID: {{ $bestilling['id'] }}</p>
