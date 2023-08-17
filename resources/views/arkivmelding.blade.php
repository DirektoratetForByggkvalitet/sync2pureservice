<h2>{{ $subject }}</h2>
<ul>
    <li>Opprettet: {{ $msg->createdDtHr() }}</li>
    <li>Forventet svardato: {{ $msg->expectedResponseDtHr() }}</li>
    <li>Hoveddokument: {{ $msg->mainDocument }}</li>
    <li>Antall vedlegg: {{ count($msg->attachments) }}</li>
</ul>
<p>Se vedleggene for innholdet i forsendelsen.</p>

