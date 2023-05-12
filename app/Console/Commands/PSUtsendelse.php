<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\{Tools, Pureservice};
use Illuminate\Support\{Arr, Str, Collection};
use App\Models\{Company, User, Ticket, TicketCommunication};

class PSUtsendelse extends Command {
    protected float $start;
    protected Pureservice $ps;
    protected int|null $ticketId = null;
    protected int $ticketCount = 0;
    protected array $recipientListAssetType;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'pureservice:utsendelse {--reset-db : Nullstiller databasen før kjøring}';

    protected $version = '0.2';
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
        $this->newLine(2);

        $this->info(Tools::ts().'Setter opp miljøet...');
        if ($this->option('reset-db')):
            $this->info('### NULLSTILLER DATABASEN ###');
            $this->call('migrate:fresh');
            $this->newLine(2);
        endif;

        $this->info(Tools::ts().'Kobler til Pureservice');
        $this->comment(Tools::l3().'Bruker '.config('pureservice.api_url'));
        $this->ps = new Pureservice();
        $this->recipientListAssetType = $this->ps->getEntityByName('assettype', config('pureservice.dispatch.listAssetName'));

        $this->newLine();

        $uri = '/communication/?include=ticket,ticket.user,ticket.user.company,ticket.user.emailaddresses,ticket.user.company.emailaddresses';
        $uri .= '&sortBy=created DESC&filter=customtype.name == "' .
            config('pureservice.dispatch.commTypeName') .
            '" AND ticket.status.name == "' .
            config('pureservice.dispatch.status') . '"';

        $result = $this->ps->apiGet($uri);

        //$this->line(Tools::l1().'Mellomlagrer virksomhet(er) og bruker(e)');

        if (count($result['communications']) == 0):
            $this->info('Ingen saker til behandling. Avslutter.');
            return Command::SUCCESS;
        endif;

        $psUsers = collect($result['linked']['users'])
            ->mapInto(User::class)
            ->each(function (User $item, int $key) use ($result) {
                if ($email = collect($result['linked']['emailaddresses'])->firstWhere('userId', $item->id)):
                    $item->email = $email['email'];
                endif;
                if ($existing = User::firstWhere('id', $item->id)) $item = $existing;
                $item->save();
            });
        $psCompanies = collect($result['linked']['companies'])
            ->mapInto(Company::class)
            ->each(function(Company $item, int $key) use ($result) {

                if ($email = collect($result['linked']['companyemailaddresses'])->firstWhere('companyId', $item->id)):
                    $item->email = $email['email'];
                endif;
                if ($existing = Company::firstWhere('id', $item->id)) $item = $existing;
                $item->save();
            });
        $psTickets = collect($result['linked']['tickets'])
            ->mapInto(Ticket::class)
            ->each(function (Ticket $item, int $key) use ($result) {
                if ($existing = Ticket::firstWhere('id', $item->id)):
                    $item = $existing;
                endif;
                $item->recipients()->attach(User::firstWhere('id', $item->userId));
                $item->save();
            });
        $psCommunications = collect($result['communications'])
            ->mapInto(TicketCommunication::class)
            ->each(function (TicketCommunication $item, int $key) use ($result) {
                if ($existing = TicketCommunication::firstWhere('id', $item->id)) $item = $existing;
                $item->save();
            });
        $this->ticketCount = $psTickets->count();
        unset($result, $psUsers, $psComanies, $psTickets, $psCommunications);
        $this->line(Tools::l1().'Fant '.$this->ticketCount.' sak'.($this->ticketCount > 1 ? 'er': ''). ' som skal behandles');
        $this->newLine();

        $this->checkRelationships();

        return Command::SUCCESS;
    }

    protected function checkRelationships(): void {
        foreach (Ticket::lazy() as $ticket):
            $this->info(Tools::l2().'Behandler \''.$ticket->getTicketSlug().' '.$ticket->subject.'\''.' ID: '.$ticket->id);

            $uri = '/relationship/'.$ticket->id.'/fromTicket?include=toAsset&filter=toAsset.typeId == '.$this->recipientListAssetType['id'];
            if ($relatedLists = $this->ps->apiGet($uri)):
                if (count($relatedLists['relationships']) > 0):
                    foreach ($relatedLists['linked']['assets'] as $list):
                        $uri = '/relationship/'.$list['id'].'/fromAsset?include=toUser,toCompany,toUser.emailaddresses,toCompany.emailaddresses';
                        if ($listRelations = $this->ps->apiGet($uri)):
                            if (count($listRelations['linked']['users'])):
                                $users = collect($listRelations['linked']['users'])
                                ->mapInto(User::class)
                                ->each(function (User $user, int $key) use ($listRelations, $ticket) {
                                    if ($email = collect($listRelations['linked']['emailaddresses'])->firstWhere('userId', $user->id)):
                                        $user->email = $email['email'];
                                    endif;
                                    if ($existing = User::firstWhere('id', $user->id)):
                                        $user = $existing;
                                    endif;
                                    $user->save();
                                    $ticket->recipients()->attach($user);
                                });
                            endif;
                            if (count($listRelations['linked']['companies'])):
                                $companies = collect($listRelations['linked']['companies'])
                                ->mapInto(Company::class)
                                ->each(function (Company $company, int $key) use ($listRelations, $ticket){
                                    if ($email = collect($listRelations['linked']['companyemailaddresses'])->firstWhere('companyId', $company->id)):
                                        $company->email = $email['email'];
                                    endif;
                                    if ($existing = Company::firstWhere('id', $company->id)) $company = $existing;
                                    $company->save();
                                    $ticket->recipientCompanies()->attach($company);
                                });
                            endif;
                        endif;
                    endforeach;
                endif;
            endif;

        endforeach;

    }
}
