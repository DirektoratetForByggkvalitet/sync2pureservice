@php
    use Illuminate\Support\{Arr};

    $attachmentCount = collect($msg->attachments)->filter(function (string $value, int $key) {
        return basename($value) != 'arkivmelding.xml';
    })->count();
    $saksansvarlig = Arr::get($arkivmelding, 'mappe.saksansvarlig', 'Ikke oppgitt');
    $saksansvarlig = is_array($saksansvarlig) ? 'Ikke oppgitt' : $saksansvarlig;
@endphp
<h2>{{ $subject }}</h2>
<ul>
@if (isset($arkivmelding))
    <li>Saksdato: {{ Arr::get($arkivmelding, 'mappe.saksdato', 'Ikke oppgitt') }}</li>
    <li>Saksansvarlig: {{ $saksansvarlig }}
@else
    <li>Opprettet: {{ $msg->getCreatedDtHr() }}</li>
@endif
    <li>Forventet svardato: {{ $msg->getExpectedResponseDtHr() }}</li>
    <li>Hoveddokument: {{ $msg->mainDocument }}</li>
    <li>Antall dokumenter: {{ $attachmentCount }}</li>
</ul>
<p>Selve innholdet i forsendelsen ligger som vedlegg til saken.</p>
<p>Meldings-ID: {{ $msg->messageId }}</p>

