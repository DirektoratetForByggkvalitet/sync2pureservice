<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\{Tools, Pureservice};
use Illuminate\Support\{Arr, Str, Collection};
use App\Models\{Company, Ticket, TicketCommunication};

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

        if (count($result['communications']) == 0):
            $this->info('Ingen saker til behandling. Avslutter.');
            return Command::SUCCESS;
        endif;

        $this->psTickets = collect($result['linked']['tickets']);
        $this->ticketCount = $this->psTickets->count();
        $this->line(Tools::l1().'Fant '.$this->ticketCount.' sak'.($this->ticketCount > 1 ? 'er': ''). ' som skal behandles');
        $this->newLine();

        $this->line(Tools::l1().'Mellomlagrer virksomhet(er) og bruker(e)');
        $this->psUsers = collect($result['linked']['users']);
        $this->psCompanies = collect($result['linked']['companies'])->mapInto(Company::class);
        $this->psCompanies->each(function (Company $company, int $key) {
            $company->save();
        });
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
