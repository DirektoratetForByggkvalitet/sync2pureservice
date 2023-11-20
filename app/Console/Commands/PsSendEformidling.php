<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\{Arr, Str};
use App\Services\{Eformidling, PsApi, Tools};
use App\Models\{Ticket, Message, Company, TicketCommunication, User};

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
    protected Eformidling $ef;
    protected Company $receiver;
    protected int $reqNo;


    /**
     * Execute the console command.
     */
    public function handle() {
        if ($this->reqNo = $this->argument('requestNumber')):
            $this->ps = new PsApi();
            $query = [
                'include' => 'user,user.company,user.emailaddress',
            ];
            $uri = '/ticket/'.$this->reqNo.'/requestNumber/';
            $response = $this->ps->apiQuery($uri, $query, true);
            if ($response->successful()):
                $ticket = collect($response->json('tickets'))->mapInto(Ticket::class)->first();
                $ticketUser = collect($response->json('linked.users'))->mapInto(User::class)->first();
                $ticketUser->email = $response->json('linked.emailaddresses.0.email');
                $ticketCompany = collect($response->json('linked.companies'))->mapInto(Company::class)->first();
                $ticket->save();
            else:
                $this->error('Fant ikke oppgitt saksnummer');
                return Command::FAILURE;
            endif;

            //$ticket = $this->ps->getTicketFromPureservice($this->reqNo, true, $query);
            $this->line(Tools::L1.'Behandler saksnr '.$this->reqNo.' - \''.$ticket->subject.'\':');
            if ($ticketCompany->organizationNumber):
                $this->line(Tools::L2.'Skal sendes til foretak: '.$ticketCompany->name.' - '.$ticketCompany->organizationNumber);
                $this->line(Tools::L2.'Sluttbruker: '.$ticketUser->firstName.' '.$ticketUser->lastName.' - '.$ticketUser->email);
            else:
                $this->error(Tools::L2.'Kan ikke sende med eFormidling. Foretaket '.$ticketCompany->name.' mangler organisasjonsnr.');
                return Command::FAILURE;
            endif;
            // Henter inn utgående kommunikasjon eller løsningstekst.
            $uri = '/communication/';
            $query = [
                'filter' => 'ticketID == '.$ticket->id. ' AND direction == '.config('pureservice.comms.direction.out'),
                'sort' => 'modified DESC',
            ];
            $response = $this->ps->apiQuery($uri, $query, true);
            if ($response->successful() && count($response->json('communications'))):
                $communication = collect($response->json('communications'))->mapInto(TicketCommunication::class)->first();
                $communication->save();
            endif;
        else:
            $this->error('Ingen saksnummer oppgitt. Avbryter');
            return Command::FAILURE;
        endif;
    }
}
