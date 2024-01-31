<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\{ExcelLookup, Tools, Enhetsregisteret, PsApi};
use App\Models\{Company, User};
use Illuminate\Support\{Str, Arr};

class Offentlige2Ps extends Command {
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'pureservice:offentlige2ps
        {--no-sync : Hopper over lagring til Pureservice}
        {--reset-db : Nullstiller databasen før oppstart (anbefalt)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Importerer offentlige instanser inn i Pureservice, ved å opprette dem som firma, samt eFormidling- og postmottak-brukere';

    protected float $start;

    protected int $foundBrreg = 0;
    protected int $addedToDB = 0;
    protected array $brrCounters = [];

    protected int $usersProcessed= 0;
    protected int $usersUpdated = 0;
    protected int $usersCreated = 0;
    protected int $usersInDB = 0;

    protected int $companiesProcessed = 0;
    protected int $companiesUpdated = 0;
    protected int $companiesCreated = 0;

    /**
     * Execute the console command.
     */
    public function handle(): int {
        $this->start = microtime(true);
        if ($this->option('reset-db')):
            $this->info('### NULLSTILLER DATABASEN ###');
            $this->call('migrate:fresh', ['--force']);
            $this->newLine(2);
        endif;
        $this->newLine(2);
        $this->info('####################################');
        $this->info(' DEL 1: Hent virksomheter fra BRREG');
        $this->info('####################################');
        $this->importFraEnhetsregisteret(config('enhetsregisteret.search'), true);
        $this->newLine();

        $resultTable = [];
        foreach ($this->brrCounters as $key => $value):
            $resultTable[] = [Str::headline($key), $value];
        endforeach;
        $resultTable[] = ['Totalt fra BRREG', $this->foundBrreg];
        $resultTable[] = ['---', '---'];
        $resultTable[] = ['Ikke importerte (*)', $this->foundBrreg - $this->addedToDB];
        $resultTable[] = ['Importert til DB', Company::count()];
        $this->table(
            ['', 'Antall'],
            $resultTable,
        );
        $this->comment('(*) Dersom virksomheten er merket \'under oppføring\' blir den ikke lagt inn i DB');

        $this->newLine(2);
        $this->info('######################################');
        $this->info(' DEL 2: Synkronisering med Pureservice');
        $this->info('######################################');
        $this->newLine();
        if ($this->option('no-sync')):
            $this->comment('Hoppet over synkronisering med Pureservice fordi \'--no-sync\' ble oppgitt');
        else:
            $this->sync2Pureservice();
        endif;

        $this->newLine(2);
        $this->info('######################################');
        $this->info(' OPPSUMMERING');
        $this->info('######################################');
        $this->table(
            ['', 'I databasen', 'Prosesserte'],
            [
                ['Virksomheter', Company::count(), $this->companiesProcessed],
                ['Brukerkontoer', User::count(), $this->usersProcessed],
            ]
        );
        if ($noEmails = Company::where('email', null)):
            $this->newLine(2);
        endif;

        $this->info('Operasjonen kjørte i '.round(microtime(true) - $this->start, 0).' sekunder');
        return Command::SUCCESS;
    }

    /**
     * Importerer instanser fra Enhetsregisteret og mellomlagrer dem i databasen
     */
    protected function importFraEnhetsregisteret(string|array $aParams, $resolveUnderlaying = false) : void {
        if (!is_array($aParams)) $aUri = ['parametre' => $aParams];
        $brApi = new Enhetsregisteret();
        // Søker opp enheter fra enhetsregisteret
        foreach ($aParams as $name => $params):
            $this->newLine();
            $this->line(Tools::L1.'Behandler '.Str::headline($name));
            $result = $brApi->apiQuery('enheter', $params);
            $this->foundBrreg += Arr::get($result, 'page.totalElements');
            $this->brrCounters[$name] = Arr::get($result, 'page.totalElements');
            if ($this->foundBrreg > 0):
                $this->storeCompanies(Arr::get($result, '_embedded.enheter'));
                if ($resolveUnderlaying && $params['organisasjonsform'] == 'STAT'):
                    foreach ($result['_embedded']['enheter'] as $main):
                        $this->newLine();
                        $this->info(Tools::L2.'Finner underliggende virksomheter for '.$main['navn'].' - '.$main['organisasjonsnummer']);
                        $this->newLine();

                        $params = config('enhetsregisteret.underliggende');
                        $params['overordnetEnhet'] = Str::replace('[ORGNR]', $main['organisasjonsnummer'], $params['overordnetEnhet']);
                        $res = $brApi->apiQuery('enheter', $params);
                        $this->foundBrreg += Arr::get($res, 'page.totalElements');
                        $this->brrCounters[$name] += Arr::get($res, 'page.totalElements');
                        if (Arr::get($res, 'page.totalElements') > 0) $this->storeCompanies(Arr::get($res, '_embedded.enheter'), $main['navn'].' - '.$main['organisasjonsnummer']);
                        unset($res);
                    endforeach;
                endif;
            endif;
        endforeach;
        unset($result);
        // Behandler enhetene som ble funnet og mellomlagrer dem i databasen
    }

    protected function storeCompanies(array $companies, $overliggende = null): void {
        $eData = ExcelLookup::loadData(); // Collection eller null
        foreach ($companies as $company):
            if (Str::contains($company['navn'], "under forhåndsregistrering", true)) continue;
            $this->info(Tools::L1.$company['navn'].' - '. $company['organisasjonsnummer']);
            if ($newCompany = Company::firstWhere('organizationNumber', $company['organisasjonsnummer'])):
                $this->comment(Tools::L2.'Fant virksomheten i DB');
            else:
                $this->comment(Tools::L2.'Lagrer virksomheten i DB');
                $fields = [
                    'name' => Str::replace(' Og ', ' og ', Str::replace(' I ', ' i ', Str::title(Str::squish($company['navn'])))),
                    'organizationNumber' => Str::squish($company['organisasjonsnummer']),
                    'website' => isset($company['hjemmeside']) ? Str::squish($company['hjemmeside']) : null,
                    'category' => config('pureservice.company.categoryMap.'.Arr::get($company, 'organisasjonsform.kode'), null),
                    'notes' => ($overliggende) ? 'Tilhører ' . Str::replace(' Og ', ' og ', Str::replace(' I ', ' i ', Str::title(Str::squish($overliggende)))) : '',
                    'email' => null,
                    'phone' => null,
                ];
                $newCompany = Company::factory()->make($fields);
            endif;
            $this->addedToDB++;
            // Finner e-postadresse fra Excel-fil
            if ($eData && $foundData = $eData->firstWhere('regnr', $newCompany->organizationNumber)):
                $newCompany->email = Tools::cleanEmail($foundData['e-post']);
            endif;

            if ($newCompany->category == config('pureservice.company.categoryMap.KOMM')):
                // Setter kommunenr for kommune
                $newCompany->companyNumber = Arr::get($company, 'forretningsadresse.kommunenummer');
            elseif ($newCompany->category == config('pureservice.company.categoryMap.FYLK')):
                // Setter kommunenr for fylkeskommune
                $newCompany->companyNumber = substr(Arr::get($company, 'forretningsadresse.kommunenummer'), 0, 2).'00';
            endif;

            if (Str::endsWith($newCompany->website, '/')):
                $newCompany->website = Str::beforeLast($newCompany->website, '/');
            endif;

            $newCompany->save();

        endforeach;
    }

    /**
     * Oppretter eller endrer på virksomhet og bruker i Pureservice
     */
    protected function sync2pureservice(): void {
        $count = 0;
        $ps = new PsApi();

        $this->newLine();
        $this->comment('Oppdaterer virksomheter mot '.config('pureservice.api_url'));
        $this->newLine();

        $bar = $this->output->createProgressBar(Company::count());
        $bar->setFormat('verbose');
        $bar->start();
        foreach (Company::lazy() as $company):
            $this->companiesProcessed++;
            $company->addOrUpdatePS($ps);
            $company->createStandardUsers();
            $bar->advance();
        endforeach;
        $bar->finish();

        $this->newLine(2);
        $this->comment('Oppdaterer brukere mot '.config('pureservice.api_url'));
        $this->newLine();
        $bar = $this->output->createProgressBar(User::count());
        $bar->setFormat('verbose');
        $bar->start();
        foreach (User::lazy() as $user):
            $this->usersProcessed++;
            $user->addOrUpdatePS($ps);
            $bar->advance();
        endforeach;
        $bar->finish();
    }
}
