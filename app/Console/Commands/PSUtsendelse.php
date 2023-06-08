<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\{Tools, PsApi, Eformidling};
use Illuminate\Support\{Arr, Str, Collection};
use App\Models\{Company, User, Ticket, TicketCommunication};
use App\Mail\TicketMessage;
use Illuminate\Support\Facades\{Mail, Blade};


class PSUtsendelse extends Command {
    protected float $start;
    protected PsApi $ps;
    protected Eformidling $ef;
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
        $this->comment(Tools::l3().'Bruker '.config('pureservice.api.url'));
        $this->ps = new PsApi();
        $this->recipientListAssetType = $this->ps->getEntityByName('assettype', config('pureservice.dispatch.assetTypeName'));

        $this->newLine();

        /**
         * API-kall som henter inn kommunikasjonstype av riktig type, der saken venter på ekspedering
         * Det smarte her er at vi får all informasjon vi trenger om utsendelsen: saken, brukeren, firmaet
         */
        $uri = '/communication/';
        $query = [
            'include' => 'ticket,ticket.user,ticket.user.company,ticket.user.emailaddresses,ticket.user.company.emailaddresses',
            'sortBy' => 'created',
            'filter' => 'customtype.name == "' .
                config('pureservice.dispatch.commTypeName') .
                '" AND ticket.status.name == "' .
                config('pureservice.dispatch.status') . '"',
        ];
        $result = $this->ps->apiGet($uri, false, null, $query);

        // Hvis ingen blir funnet, stopper vi videre behandling.
        if (count($result['communications']) == 0):
            $this->info('Ingen saker til behandling. Avslutter.');
            return Command::SUCCESS;
        endif;

        /**
         * Fortsetter med å legge relaterte firma inn i databasen
         */
        $psCompanies = collect($result['linked']['companies'])
            ->mapInto(Company::class)
            ->each(function(Company $item, int $key) use ($result) {
                if ($email = collect($result['linked']['companyemailaddresses'])->firstWhere('companyId', $item->id)):
                    $item->email = $email['email'];
                endif;
                if ($existing = Company::firstWhere('id', $item->id)) $item = $existing;
                $item->save();
            }
        );

        /**
         * Fortsetter med å legge relaterte brukere inn i lokal DB
         */
        $psUsers = collect($result['linked']['users'])
            ->mapInto(User::class)
            ->each(function (User $item, int $key) use ($result) {
                if ($email = collect($result['linked']['emailaddresses'])->firstWhere('userId', $item->id)):
                    $item->email = $email['email'];
                endif;
                if ($existing = User::firstWhere('id', $item->id)) $item = $existing;
                $item->save();
            }
        );

        /**
         * Henter inn sakene til DB
         */
        $psTickets = collect($result['linked']['tickets'])
            ->mapInto(Ticket::class)
            ->each(function (Ticket $item, int $key) use ($result) {
                if ($existing = Ticket::firstWhere('id', $item->id)):
                    $item = $existing;
                endif;
                $item->save();
                /*
                if ($attachedUser = User::firstWhere('id', $item->userId)):
                    $item->recipients()->attach($attachedUser->internal_id);
                endif;
                if ($attachedCompany = Company::firstWhere('id', $item->companyId)):
                    $item->recipientCompanies()->attach($attachedCompany->internal_id);
                endif;

                $item->save();
                */
            }
        );

        /**
         * Til slutt selve kommunikasjonen
         */
        $psCommunications = collect($result['communications'])
            ->mapInto(TicketCommunication::class)
            ->each(function (TicketCommunication $item, int $key) use ($result) {
                if ($existing = TicketCommunication::firstWhere('id', $item->id)) $item = $existing;
                $item->save();
            }
        );

        $this->ticketCount = $psTickets->count();
        unset($result, $psUsers, $psComanies, $psTickets, $psCommunications);
        $this->line(Tools::l1().'Fant '.$this->ticketCount.' sak'.($this->ticketCount > 1 ? 'er': ''). ' som skal behandles');
        $this->newLine();

        /**
         * Går gjennom alle saker og kobler mottakerne fra relaterte mottakerlister til sakene
         */
        $this->line(Tools::l1().'Går gjennom sakene og henter ut mottakerlistene');
        $bar = $this->output->createProgressBar(Ticket::count());
        $bar->start();
        foreach (Ticket::lazy() as $t):
            $t->decideAction();
            $t->extractRecipientsFromAsset($this->ps, $this->recipientListAssetType);
            $t->downloadAttachments($this->ps);
            $bar->advance();
        endforeach; // Ticket as $ticket
        $bar->finish();
        $this->newLine(2);

        $this->info('Del 2: Utføre utsendelsen');
        $results = [
            'saker' => 0,
            'e-post' => 0,
            'eFormidling' => 0,
            'ikke sendt' => 0
        ];
        $this->ef = new Eformidling();
        foreach (Ticket::lazy() as $t):
            $ticketResults = [
                'e-post' => 0,
                'eFormidling' => 0,
                'ikke sendt' => 0,
            ];
            // Først brukere. De har ikke orgnr, og må dermed kontaktes per e-post
            $this->line(Tools::l1().'Går gjennom sak nr '.$t->requestNumber);
            $this->line(Tools::l2().'Personer:');
            $bar = $this->output->createProgressBar($t->recipients()->count());
            $bar->start();
            foreach ($t->recipients()->lazy() as $user):
                $user->name = $user->firstName.' '.$user->lastName;
                //$this->line(Tools::l2().$user->name.' - '.$user->email);
                if (!Str::endsWith($user->email, '.local')):
                    // Send e-post til brukeren
                    Mail::to($user)->send(new TicketMessage($t));
                    $ticketResults['e-post']++;
                endif;
                $bar->advance();
            endforeach;
            $bar->finish();
            $this->newLine();

            // Går gjennom tilknyttede virksomheter
            $this->line(Tools::l2().'Virksomheter:');
            $bar = $this->output->createProgressBar($t->recipientCompanies()->count());
            $bar->start();
            foreach ($t->recipientCompanies()->lazy() as $company):
                //$this->line(Tools::l2().$company->name.' - '.$company->email);
                if ($t->eFormidling && $company->organizationNumber):
                    $this->ef->createAndSendMessage($t, $company);
                    $ticketResults['eFormidling']++;
                elseif (Str::endsWith($company->email, '.local')):
                    // lokal adresse, hopper over
                    continue;
                elseif ($company->email == null):
                else: // Sender per e-post
                    Mail::to($company)->send(new TicketMessage($t));
                    $ticketResults['e-post']++;
                endif;
                $bar->advance();
            endforeach;
            $bar->finish();
            // Løser saken med en rapport
            $this->newLine();
            $statusId = $this->ps->getEntityId('status', config('pureservice.dispatch.finishStatus', 'Løst'));
            $solution = Blade::render('report', ['ticket' => $t, 'results' => $ticketResults]);
            $body = [
                'statusId' => $statusId,
                'solution' => $solution,
            ];
            $uri = '/ticket/' . $t->id;
            if ($updated = $this->ps->apiPatch($uri, $body, true)):
                $this->line(Tools::l2().'Saken har blitt satt til løst.');
            endif;

            $results['saker']++;
            foreach ($ticketResults as $key => $value):
                $results[$key] += $value;
            endforeach;

         endforeach; // Ticket

        $this->newLine(2);
        $this->info(Tools::l1().'Ferdig. Operasjonen ble fullført på '.round(microtime(true) - $this->start, 0).' sekunder');
        $this->line('Resultater');
        $this->line('----------');
        $this->line('Antall saker: '.$results['saker']);
        $this->line('Antall e-post sendt: '.$results['e-post']);
        $this->line('Antall eFormidling-forsendelser: '.$results['eFormidling']);
        $this->line('Antall manglende adresser: '.$results['ikke sendt']);

        return Command::SUCCESS;
    }
/*
    protected function makePDFTest () {
        $data = [
            'title' => 'Jada!',
            'content' => '<H2>Gratulerer</H2>'.PHP_EOL.'<p>Du har vunnet!</p>',
        ];
        $pdf = DomPDF::loadView('message', $data);
        file_exists(storage_path('pdf/test.pdf')) ? unlink(storage_path('pdf/test.pdf')): true;
        $pdf->save(storage_path('pdf/test.pdf'));
    }
*/
}
