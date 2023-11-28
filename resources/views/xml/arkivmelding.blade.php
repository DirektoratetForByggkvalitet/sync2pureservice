@php
    use Illuminate\Support\Facades\{Storage};
    use App\Services\Tools;
    use App\Models\{Company, Ticket};

    $docNo = 0;
    $receiver = $msg->receiver();
    $ticketStatus = $ticket->getStatus();
@endphp
{!! '<'.'?xml version="1.0" encoding="utf-8"?>' !!}
<arkivmelding xmlns="http://www.arkivverket.no/standarder/noark5/arkivmelding"
    xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
    xsi:schemaLocation="http://www.arkivverket.no/standarder/noark5/arkivmelding/arkivmelding.xsd">
    <system>{{ config('eformidling.system.name') }}</system>
    <meldingsId>{{ $msg->messageId }}</meldingsId>
    <tidspunkt>{{ $msg->getCreatedDTLocalString() }}</tidspunkt>
    <antallFiler>{{ count($msg->attachments) }}</antallFiler>
    <mappe xsi:type="saksmappe">
        <systemID>{{ config('eformidling.system.systemId') }}</systemID>
        <saksdato>{{ $msg->getCreatedDTLocalString() }}</saksdato>
        <saksstatus>{{ $ticketStatus }}</saksstatus>
        <opprettetDato>{{ $msg->getOpprettetDato() }}</opprettetDato>
        <opprettetAv>{{ $msg->getOpprettetAv() }}</opprettetAv>
        <basisregistrering xsi:type="journalpost">
            <systemID>{{ config('eformidling.system.systemId') }}</systemID>
            <opprettetDato>{{ $msg->getOpprettetDato() }}</opprettetDato>
            <opprettetAv>{{ $msg->getOpprettetAv() }}</opprettetAv>
            <arkivertDato>{{ $msg->getArkivertDato() }}</arkivertDato>
            <arkivertAv>{{ $msg->getArkivertAv() }}</arkivertAv>
            <referanseArkivdel>Fagsystem Pureservice</referanseArkivdel>
            <tittel>{{ $ticket->subject }}</tittel>
            <offentligTittel>{{ $ticket->subject }}</offentligTittel>
            <journalposttype>{{ $ticket->getType() }}</journalposttype>
            <journalstatus>{{ $ticketStatus }}</journalstatus>
            <beskrivelse>
                Se hoveddokumentet for innhold.
            </beskrivelse>
            <virksomhetsspesifikkeMetadata>
                <INC>{{ $ticket->requestNumber }}</INC>
            </virksomhetsspesifikkeMetadata>

@foreach ($msg->attachments as $doc)
    @if (Str::endsWith($doc, 'arkivmelding.xml'))
        @continue
    @endif
    @php($docNo++)

            <dokumentbeskrivelse>
                <dokumenttype>{{ Storage::mimeType($doc) }}</dokumenttype>
                <dokumentstatus>{{ config('eformidling.arkivmelding.documentStatus') }}</dokumentstatus>
                <tittel>{{ Tools::fileNameFromStoragePath($doc, true) }}</tittel>
                <opprettetDato>{{ Tools::atomTs(Storage::lastModified($doc)) }}</opprettetDato>
                <opprettetAv>{{ $msg->getOpprettetAv() }}</opprettetAv>
                <tilknyttetRegistreringSom>
@if (basename($doc) == $msg->mainDocument)
                    {{ config('eformidling.arkivmelding.mainDocument') }}
@else
                    {{ config('eformidling.arkivmelding.attachment') }}
@endif
                </tilknyttetRegistreringSom>
                <dokumentnummer>{{ $docNo }}</dokumentnummer>
                <tilknyttetDato>{{ $msg->getOpprettetDato() }}</tilknyttetDato>
                <tilknyttetAv>{{ $msg->getOpprettetAv() }}</tilknyttetAv>
                <dokumentobjekt>
                    <versjonsnummer>1</versjonsnummer>
                    <variantformat>{{ config('eformidling.arkivmelding.documentVariant') }}</variantformat>
                    <opprettetDato>{{ Tools::atomTs(Storage::lastModified($doc)) }}</opprettetDato>
                    <opprettetAv>{{ $msg->getOpprettetAv() }}</opprettetAv>
                    <referanseDokumentfil>{{ Tools::fileNameFromStoragePath($doc) }}</referanseDokumentfil>
                </dokumentobjekt>
            </dokumentbeskrivelse>
@endforeach

        </basisregistrering>
    </mappe>
</arkivmelding>
