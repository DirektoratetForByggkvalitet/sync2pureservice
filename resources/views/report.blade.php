<h2>Rapport etter utsending</h2>
@if (count($results['sakerOpprettet']) == 0)
<p>
    Hovedkanal for utsendingen var {{ $method }}.
    Resultatet ble som følger:
</p>
<ul>
    <li>E-poster sendt: {{ $results['email'] }}</li>
    <li>eFormidling-forsendelser: {{ $results['eFormidling'] }}</li>
    <li>Mottakere med ugyldige adresser: {{ $results['skipped'] }}</li>
    <li><strong>Totalt antall mottakere: {{ $results['recipients'] }}</strong></li>
</ul>
@else
<p>
    Totalt ble {{ count($results['sakerOpprettet']) }} nye saker opprettet, og meldinger ble sendt ut fra dem.
</p>
<ul>
@foreach ($results['sakerOpprettet'] as $sak)
    <li><a href="/agent/#/ticket/{{ $sak->requestNumber }}/">Sak {{ $sak->requestNumber }}</a></li>
@endforeach
</ul>
@endif

