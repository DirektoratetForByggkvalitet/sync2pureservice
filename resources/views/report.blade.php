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
</ul>
<h2>Mottakere</h2>
<p>Utsendingen var adressert til {{ $results['recipients'] }} mottakere:</p>
<ul>
@foreach ($recipients as $recipient)
    @php
        $delivery = '[levert]';
        if (!$recipient->email) $delivery = '[ikke levert]';
    @endphp
    @if ($recipient instanceof App\Models\User)
        <li>{{ $recipient->firstName . ' ' . $recipient->lastName }}
        @if ($recipient->companyId && $c = App\Models\Company::firstWhere('id', $recipient->companyId))
            - {{ $c->name }} {{ $delivery }}
        @endif
        </li>
    @else
        <li>{{ $recipient->name }} {{ $delivery }}</li>
    @endif
@endforeach
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

