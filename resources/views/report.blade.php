<h2>Rapport etter utsending</h2>

<p>
    Hovedkanal for utsendingen var {{ $ticket->eFormidling ? 'eFormidling' : 'e-post' }}.
    Resultatet ble som følger:
</p>
<ul>
    <li>Totalt antall mottakere: {{ $ticket->recipients()->count() + $ticket->recipientCompanies()->count() }}</li>
    <li>E-poster sendt: {{ $results['e-post'] }}</li>
    <li>eFormidling-forsendelser: {{ $results['eFormidling'] }}</li>
    <li>Mottakere med ugyldige adresser: {{ $results['ikke sendt'] }}</li>
</ul>
