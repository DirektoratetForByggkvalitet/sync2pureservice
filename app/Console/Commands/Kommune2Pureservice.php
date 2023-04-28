<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\{ExcelLookup, Pureservice, Tools, Enhetsregisteret};
use App\Models\{Company, User};
use Illuminate\Support\{Str, Arr};

class Kommune2Pureservice extends Command
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

    /**
     * Execute the console command.
     */
    public function handle(): int {
        $this->start = microtime(true);
        $this->importFromEnhetsregisteret(config('enhetsregisteret.search'), true);

        //$this->sync2Pureservice();
        return Command::SUCCESS;
    }

    /**
     * Importerer instanser fra Enhetsregisteret og mellomlagrer dem i databasen
     */
    protected function importFromEnhetsregisteret(string|array $aUri, $resolveUnderlaying = false) : void {
        if (!is_array($aUri)) $aUri = ['url' => $aUri];
        $brApi = new Enhetsregisteret();
        // Søker opp enheter fra enhetsregisteret
        foreach ($aUri as $name => $uri):
            //$this->line(Tools::l1().'Behandler '.$name);
            $result = $brApi->apiGet($uri);
            if (Arr::get($result, 'page.totalElements') > 0):
                $this->storeCompanies(Arr::get($result, '_embedded.enheter'));
                if ($resolveUnderlaying && Str::contains($uri, 'STAT')):
                    $underlings = [];
                    foreach ($result['_embedded']['enheter'] as $main):
                        //$this->line(Tools::l1().'Finner underliggende virksomheter for '.$main['navn'].' - '.$main['organisasjonsnummer']);
                        $addr = Str::replace('[ORGNR]', $main['organisasjonsnummer'], config('enhetsregisteret.underliggende'));
                        $res = $brApi->apiGet($addr);
                        if (Arr::get($res, 'page.totalElements') > 0) $this->storeCompanies(Arr::get($res, '_embedded.enheter'));
                        unset($res);
                    endforeach;
                endif;
            endif;
            unset($result);
        endforeach;
        // Behandler enhetene som ble funnet og mellomlagrer dem i databasen
    }

    protected function storeCompanies(array $companies) {
        $eLookup = new ExcelLookup();
        foreach ($companies as $company):
            if (Str::contains($company['navn'], "under forhåndsregistrering", true)) continue;

            $this->line(Tools::l1().'Lagrer virksomheten '.$company['navn'].' - '.$company['organisasjonsnummer']);
            $newCompany = Company::create([
                'name' => Str::title($company['navn']),
                'organizationalNumber' => $company['organisasjonsnummer'],
                'website' => isset($company['hjemmeside']) ? $company['hjemmeside'] : null,
                'companyNumber' => null,
            ]);
            if (Arr::get($company, 'organisasjonsform.kode') == 'KOMM'):
                $newCompany->companyNumber = Arr::get($company, 'forretningsadresse.kommunenummer');
            elseif (Arr::get($company, 'organisasjonsform.kode') == 'FYLK'):
                $newCompany->companyNumber = substr(Arr::get($company, 'forretningsadresse.kommunenummer'), 0, 2).'00';
            endif;
            if ($newCompany->companyNumber) $this->line(Tools::l2().'Kommunenr: '.$company->companyNumber);
            if ($newCompany->companyNumber && $excelData = $eLookup->findKnr($newCompany->companyNumber)):
                $newCompany->email = $excelData['e-post'];
            endif;
            $newCompany->save();

            // Oppretter SvarUt-bruker for virksomheten
            $this->line(Tools::l2().'Oppretter SvarUt-bruker');

            $svarUtUser = $newCompany->users()->create([
                'firstName' => 'SvarUt',
                'lastName' => $newCompany->name,
                'email' => $newCompany->organizationalNumber.'.no_email@pureservice.local',
            ]);
            $svarUtUser->save();
            // Oppretter postmottak-bruker dersom denne finnes
            if ($newCompany->email != null):
                $this->line(Tools::l2().'Oppretter postmottak: '.$newCompany->email);
                $postmottak = $newCompany->users()->create([
                    'firstName' => 'Postmottak',
                    'lastName' => $newCompany->name,
                    'email' => $newCompany->email,
                ]);
                $postmottak->save();
            endif;
        endforeach;
    }

    /**
     * Oppretter eller endrer på virksomhet og bruker i Pureservice
     */
    private function sync2pureservice(): void {
        $ps = new Pureservice();
        $categoryField = config('pureservice.company.categoryfield', 'cf_1');

        foreach (Company::all() as $company):
            // Sjekker om virksomheten finnes i Pureservice
            $psCompany = $ps->findCompany($company->organizationalNumber, $company->name);
            if (!$psCompany): // Virksomheten må opprettes i Pureservice
                $psCompany = $ps->addCompany($company->name, $company->organizationalNumber, $company->email, $company->phone);
            else:
                // Sjekker om det er behov for å endre i Pureservice
                if (
                    $company->organizationalNumber != $psCompany['organizationNumber'] ||
                    $company->email != data_get($psCompany, 'linked.companyemailaddresses.0.email') ||
                    $company->companyNumber != $psCompany['companyNumber'] ||
                    $company->website != $psCompany['website'] ||
                    $company->notes != $psCompany['notes'] ||
                    $company->category != $psCompany[$categoryField]
                ):
                endif;
            endif;
            $company->externalId = $psCompany['id'];
            $company->save();
        endforeach;
    }
}
