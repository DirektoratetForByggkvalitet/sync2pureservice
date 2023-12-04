<h2>Rapport etter utsending</h2>
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
