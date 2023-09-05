@php
    use Illuminate\Support\{Arr};

    $dlPath = $msg->downloadPath();
    $attachmentCount = collect($msg->attachments)->filter(function (string $value, int $key) {
        return basename($value) != 'arkivmelding.xml';
    })->count();
@endphp
<h2>{{ $subject }}</h2>
<ul>
@if (isset($arkivmelding))
    <li>Saksdato: {{ Arr::get($arkivmelding, 'mappe.saksdato', 'Ikke oppgitt') }}</li>
    <li>Saksansvarlig: {{ Arr::get($arkivmelding, 'mappe.saksansvarlig', 'Ikke oppgitt') }}
@else
    <li>Opprettet: {{ $msg->getCreatedDtHr() }}</li>
@endif
    <li>Forventet svardato: {{ $msg->getExpectedResponseDtHr() }}</li>
    <li>Hoveddokument: {{ $msg->mainDocument }}</li>
    <li>Antall dokumenter: {{ $attachmentCount }}</li>
</ul>
<p>Se vedleggene til saken for innholdet i forsendelsen.</p>
<p>Meldings-ID: {{ $msg->messageId }}</p>

