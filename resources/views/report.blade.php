<h2>Forsendelsesrapport</h2>

<p>Forsendelse av saken "{{ $ticket->subject }}" er ferdig.</p>
<p>
    Forsendelsen var satt opp til å sendes ut med {{ $ticket->eFormidling ? 'eFormidling' : 'e-post' }}
    som hovedkanal. Resultatet ble som følger:
</p>
<ul>
    <li>Totalt antall mottakere: {{ $ticket->recipients()->count() + $ticket->recipientCompanies()->count() }}</li>
    <li>E-poster sendt: {{ $results['e-post'] }}</li>
    <li>eFormidling-forsendelser: {{ $results['eFormidling'] }}</li>
    <li>Mottakere med ugyldige adresser: {{ $results['ikke sendt'] }}</li>
</ul>
