<h2>Påminnelse/Tillegg</h2>
<p>
App Registration med navn "{{ $secret->appName }}" har autentiseringsobjekt(er) som 
utløper innen {{ config('appsecret.notify.days') }} dager.
</p>
<ul>
  <li>Application ID: {{ $secret->appId }}</li>
  <li>Type: {{ $secret->displayType() }}</li>
  <li>ID: {{ $secret->id }}</li>
  <li>Utløpsdato: {{ $secret->endDateTime->format('d.m.Y') }}</li>
</ul>
<p>
  Ved utløp vil integrasjoner som bruker dette autentiseringsobjektet slutte å virke. 
  Sertifikater og passord må oppdateres både i Entra ID og i systemet/systemene som bruker dem.
</p>