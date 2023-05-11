<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\{Tools, Pureservice};
use Illuminate\Support\{Arr, Str, Collection};
use App\Models\{Company, User, Ticket, TicketCommunication};

class PSUtsendelse extends Command {
    protected $start;
    protected Pureservice $ps;
    protected int|null $ticketId = null;
    protected int $ticketCount = 0;
    protected Collection $psUsers;
    protected Collection $psCompanies;
    protected Collection $psTickets;
    protected Collection $psCommunications;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'pureservice:utsendelse';

    protected $version = '0.1';
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Ser etter saker som skal ha elektronisk utsendelse og/eller masseutsendelse, og håndterer dem';

    /**
     * Execute the console command.
     */
    public function handle(): int {
        $this->start = microtime(true);
        $this->info(class_basename($this).' v'.$this->version);
        $this->line($this->description);

        $this->info(Tools::ts().'Setter opp miljøet...');

        $this->info(Tools::ts().'Kobler til Pureservice');
        $this->ps = new Pureservice();
        $this->newLine();

        $uri = '/communication/?include=ticket,ticket.user,ticket.user.company';
        $uri .= '&sortBy=created DESC&filter=customtype.name == "' .
            config('pureservice.dispatch.commTypeName') .
            '" AND ticket.status.name == "' .
            config('pureservice.dispatch.status') . '"';

        $result = $this->ps->apiGet($uri);

        $this->line(Tools::l1().'Mellomlagrer virksomhet(er) og bruker(e)');

        if (count($result['communications']) == 0):
            $this->info('Ingen saker til behandling. Avslutter.');
            return Command::SUCCESS;
        endif;

        $psUsers = collect($result['linked']['users'])
            ->mapInto(User::class)
            ->each(function (User $item, int $key){
                $item->save();
            });
        $psCompanies = collect($result['linked']['companies'])
            ->mapInto(Company::class)
            ->each(function(Company $item, int $key){
                $item->save();
            });
        $psTickets = collect($result['linked']['tickets'])
            ->mapInto(Ticket::class)
            ->each(function (Ticket $ticket, int $key) {
                $ticket->save();
            });
        $this->psCommunications = collect($result['communications'])
            ->mapInto(TicketCommunication::class)
            ->each(function (TicketCommunication $item, int $key){
                $item->save();
            });
        unset($result);
        $this->ticketCount = $psTickets->count();
        $this->line(Tools::l1().'Fant '.$this->ticketCount.' sak'.($this->ticketCount > 1 ? 'er': ''). ' som skal behandles');
        $this->newLine();

        /*
        $this->psCompanies->each(function (array $psCompany, int $key) {
            $company = Company::factory()->create([
                'externalId' => $psCompany['id'],
                'name' => $psCompany['name'],
                'organizationNumber' => $psCompany['organizationNumber'],
                'companyNumber' => $psCompany['companyNumber'],
                'website' => $psCompany['website'],
                'notes' => $psCompany['notes'],
                'category' => $psCompany[config('pureservice.company.categoryfield')],
            ]);
        });
        */
        /*
        foreach ($companies as $company):
            Company::factory()->create([
                'externalId' => $company['id'],
                'name' => $company['name'],
                'organizationNumber' => $company['organizationNumber'],
                'companyNumber' => $company['companyNumber'],
                'website' => $company['website'],
                'notes' => $company['notes'],
                'category' => $company[config('pureservice.company.categoryfield')],
            ]);
        endforeach;
        */

        return Command::SUCCESS;
    }

}
