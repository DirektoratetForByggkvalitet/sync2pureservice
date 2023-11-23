<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\{Arr, Str};
use App\Services\{Eformidling, PsApi, Tools};
use App\Models\{Ticket, Message, Company, TicketCommunication, User};

/**
 * PsSendEformidling er en kommando som er ment å kjøres som pipeline utløst av Pureservice
 */
class PsSendEformidling extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'pureservice:eformidling {requestNumber}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sender løsningen på sak oppgitt med requestNumber via eFormidling';
    protected string $version = '1.0';

    protected PsApi $ps;
    protected int $reqNo;
    protected Ticket $ticket;


    /**
     * Execute the console command.
     */
    public function handle() {
        $sent = false;
        if ($this->reqNo = $this->argument('requestNumber')):
            $this->ps = new PsApi();
            // Saksdata med kommunikasjoner og mottaker fra Pureservice
            $ticketData = $this->ps->getTicketAndCommunicationsByReqNo($this->reqNo);
            if (!$ticketData):
                $this->error('Fant ikke oppgitt saksnummer');
                return Command::FAILURE;
            endif;

            $sent = $this->sendTicketWithEformidling($ticketData);
            if ($sent):
                $this->error(Tools::L2.'Det oppsto en feil, meldingen ble ikke sendt');
                return Command::FAILURE;
            endif;
        else:
            $this->error('Ingen saksnummer oppgitt. Avbryter');
            return Command::FAILURE;
        endif;

        if ($sent):
            $this->line(Tools::L3.'Meldingen ble sendt');
            $solvedId = $this->ps->getEntityId('status', 'Lukket');
        endif;
    }

    public function sendTicketWithEformidling(array $ticketData): bool {
        $this->ticket = $ticketData['ticket'];
        $communication = $ticketData['ticketCommunications']->sortByDesc('id')->first();
        $communication->save();
        $ticketCompany = $ticketData['recipientCompany'];
        $ticketUser = $ticketData['recipientUser'];
        unset($ticketData);

        $this->line(Tools::L1.'Behandler saksnr '.$this->reqNo.' - \''.$this->ticket->subject.'\':');
        if ($ticketCompany->organizationNumber):
            $this->line(Tools::L2.'Skal sendes til foretak: '.$ticketCompany->name.' - '.$ticketCompany->organizationNumber);
            $this->line(Tools::L2.'Sluttbruker: '.$ticketUser->firstName.' '.$ticketUser->lastName.' - '.$ticketUser->email);
        else:
            $this->error(Tools::L2.'Kan ikke sende med eFormidling. Foretaket '.$ticketCompany->name.' mangler organisasjonsnr.');
            return false;
        endif;

        // Oppretter eFormidling-melding
        $message = $this->ticket->createMessage($ticketCompany);
        $this->line(Tools::L2.'Sender meldingen med ID '.$message->messageId);
        $ef = new Eformidling();
        if ($sent = $ef->sendMessage($message)):
            return true;
        else:
            return false;
        endif;

    }
}
