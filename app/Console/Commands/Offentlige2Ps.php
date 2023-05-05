<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\{ExcelLookup, Tools, Enhetsregisteret, Pureservice};
use App\Models\{Company, User};
use Illuminate\Support\{Str, Arr};

class Offentlige2Ps extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'pureservice:offentlige2ps';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Importerer offentlige instanser inn i Pureservice, ved å opprette dem som firma, samt SvarUt- og postmottak-brukere';

    protected $start;

    protected $foundBrreg = 0;

    protected $usersProcessed= 0;
    protected $usersUpdated = 0;
    protected $usersCreated = 0;
    protected $usersInDB = 0;

    protected $companiesProcessed = 0;
    protected $companiesUpdated = 0;
    protected $companiesCreated = 0;

    /**
     * Execute the console command.
     */
    public function handle(): int {
        $this->start = microtime(true);
        $this->info('Bruker Pureservice-adressen '.config('pureservice.api_url'));
        $this->newLine(2);
        $this->info('####################################');
        $this->info(' DEL 1: Hent virksomheter fra BRREG');
        $this->info('####################################');
        $this->importFraEnhetsregisteret(config('enhetsregisteret.search'), true);

        $this->newLine(2);
        $this->info('######################################');
        $this->info(' DEL 2: Synkronisering med Pureservice');
        $this->info('######################################');
        $this->newLine();
        $this->sync2Pureservice();

        $this->newLine(2);
        $this->info('######################################');
        $this->info('Oppsummering');
        $this->info('######################################');
        $this->table(
            ['', 'Hentet fra BRREG', 'Prosesserte'],
            [
                ['Virksomheter', $this->foundBrreg, $this->companiesProcessed],
                ['Brukerkontoer', $this->usersInDB, $this->usersProcessed],
            ]
        );
        $this->info('Operasjonen kjørte i '.round(microtime(true) - $this->start, 0).' sekunder');
        return Command::SUCCESS;
    }

    /**
     * Importerer instanser fra Enhetsregisteret og mellomlagrer dem i databasen
     */
    protected function importFraEnhetsregisteret(string|array $aUri, $resolveUnderlaying = false) : void {
        if (!is_array($aUri)) $aUri = ['url' => $aUri];
        $brApi = new Enhetsregisteret();
        // Søker opp enheter fra enhetsregisteret
        foreach ($aUri as $name => $uri):
            //$this->line(Tools::l1().'Behandler '.$name);
            $result = $brApi->apiGet($uri);
            $this->foundBrreg += Arr::get($result, 'page.totalElements');
            if ($this->foundBrreg > 0):
                $this->storeCompanies(Arr::get($result, '_embedded.enheter'));
                if ($resolveUnderlaying && Str::contains($uri, 'STAT')):
                    $underlings = [];
                    foreach ($result['_embedded']['enheter'] as $main):
                        //$this->line(Tools::l1().'Finner underliggende virksomheter for '.$main['navn'].' - '.$main['organisasjonsnummer']);
                        $addr = Str::replace('[ORGNR]', $main['organisasjonsnummer'], config('enhetsregisteret.underliggende'));
                        $res = $brApi->apiGet($addr);
                        $this->foundBrreg += Arr::get($res, 'page.totalElements');
                        if (Arr::get($res, 'page.totalElements') > 0) $this->storeCompanies(Arr::get($res, '_embedded.enheter'));
                        unset($res);
                    endforeach;
                endif;
            endif;
        endforeach;
        unset($result);
        // Behandler enhetene som ble funnet og mellomlagrer dem i databasen
    }

    protected function storeCompanies(array $companies) {
        $eData = ExcelLookup::loadData(); // Array eller null

        $bar = $this->output->createProgressBar(count($companies));
        $bar->start();
        foreach ($companies as $company):
            if (Str::contains($company['navn'], "under forhåndsregistrering", true)) continue;
            $this->companiesProcessed++;
            //$this->info(Tools::l1().$this->companyNo.': '.$company['navn'].' - '. $company['organisasjonsnummer']);
            if ($newCompany = Company::firstWhere('organizationNumber', $company['organisasjonsnummer'])):
                // $this->line(Tools::l2().'Fant virksomheten i DB');
            else:
                // $this->line(Tools::l2().'Lagrer virksomheten i DB');
                $fields = [
                    'name' => Str::title(Str::squish($company['navn'])),
                    'organizationNumber' => Str::squish($company['organisasjonsnummer']),
                    'website' => isset($company['hjemmeside']) ? Str::squish($company['hjemmeside']) : null,
                    'category' => config('pureservice.company.categoryMap.'.Arr::get($company, 'organisasjonsform.kode'), null),
                    'email' => null,
                    'phone' => null,
                ];
                $newCompany = Company::factory()->make($fields);
            endif;

            if ($newCompany->category == config('pureservice.company.categoryMap.KOMM')):
                // Setter kommunenr for kommune
                $newCompany->companyNumber = Arr::get($company, 'forretningsadresse.kommunenummer');
            elseif ($newCompany->category == config('pureservice.company.categoryMap.FYLK')):
                // Setter kommunenr for fylkeskommune
                $newCompany->companyNumber = substr(Arr::get($company, 'forretningsadresse.kommunenummer'), 0, 2).'00';
            endif;
            if ($newCompany->companyNumber):
                // $this->line(Tools::l2().'Kommunenr: '.$newCompany->companyNumber);
                if ($eData && $found = $eData->firstWhere('knr', $newCompany->companyNumber)):
                    $newCompany->email = Str::squish($found['e-post']);
                    $newCompany->streetAddress = Str::squish($found['adresse']);
                    $newCompany->postalCode = Str::squish($found['postnr']);
                    $newCompany->city = Str::squish($found['poststed']);
                    $newCompany->country = 'Norge';
                endif;
            endif;
            $newCompany->save();

            // Oppretter SvarUt-bruker for virksomheten
            $svarutEmail = $newCompany->organizationNumber.'@svarut.pureservice.local';
            $this->usersInDB++;
            if ($svarUtUser = $newCompany->users()->firstWhere('email', $svarutEmail)):
                //$this->line(Tools::l2().'Fant SvarUt-bruker '.$svarutEmail.' i DB');
            else:
                //$this->line(Tools::l2().'Oppretter SvarUt-bruker '.$svarutEmail.' i DB');
                $svarUtUser = $newCompany->users()->create([
                    'firstName' => 'SvarUt',
                    'lastName' => $newCompany->name,
                    'email' => $svarutEmail,
                ]);
                $svarUtUser->save();
            endif;
            // Oppretter postmottak-bruker dersom denne finnes
            if ($newCompany->email != null):
                $this->usersInDB++;
                if ($postmottak = $newCompany->users()->firstWhere('email', $newCompany->email)):
                    //$this->line(Tools::l2().'Fant postmottak: '.$newCompany->email);
                else:
                    //$this->line(Tools::l2().'Oppretter postmottak: '.$newCompany->email);
                    $postmottak = $newCompany->users()->create([
                        'firstName' => 'Postmottak',
                        'lastName' => $newCompany->name,
                        'email' => $newCompany->email,
                    ]);
                    $postmottak->save();
                endif;
            endif;
            $bar->advance();
        endforeach;
        $bar->finish();
    }

    /**
     * Oppretter eller endrer på virksomhet og bruker i Pureservice
     */
    private function sync2pureservice(): void {
        $count = 0;
        $ps = new Pureservice();

        $this->newLine();
        $this->comment('Oppdaterer virksomheter');
        $this->newLine();

        $bar = $this->output->createProgressBar(Company::count());
        $bar->start();
        foreach (Company::lazy() as $company):
            $this->companiesProcessed++;
            $company->addOrUpdatePS($ps);
            $bar->advance();
        endforeach;
        $bar->finish();

        $this->newLine();
        $this->comment('Oppdaterer brukere');
        $this->newLine();
        $bar = $this->output->createProgressBar(User::count());
        $bar->start();
        foreach (User::lazy() as $user):
            $this->usersProcessed++;
            $user->addOrUpdatePS($ps);
            $bar->advance();
        endforeach;
        $bar->finish();
    }
}
