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

    protected $l1 = '';
    protected $l2 = '> ';
    protected $l3 = '  ';
    protected $start;

    /**
     * Execute the console command.
     */
    public function handle(): int {
        $this->start = microtime(true);
        $this->importFromEnhetsregisteret(config('enhetsregisteret.search'), true);


        return Command::SUCCESS;
    }

    /**
     * Importerer instanser fra Enhetsregisteret og mellomlagrer dem i databasen
     */
    protected function importFromEnhetsregisteret(string|array $aUri, $resolveUnderlaying = false) : void {
        if (!is_array($aUri)) $aUri = ['url' => $aUri];
        $brApi = new Enhetsregisteret();
        // Søker opp enheter fra enhetsregisteret
        $companies = [];
        foreach ($aUri as $uri):
            $result = $brApi->apiGet($uri);
            $companies = array_merge($companies, $result['enheter']);
            if ($resolveUnderlaying && Str::contains($uri, 'STAT')):
                foreach ($result['enheter'] as $overliggende):
                    $addr = Str::replace(config('enhetsregisteret.underliggende'), '[ORGNR]', $overliggende['organisasjonsnummer']);
                    $result = $brApi->apiGet($addr);
                    $companies = array_merge($companies, $result['enheter']);
                endforeach;
            endif;
        endforeach;
        // Behandler enhetene som ble funnet og mellomlagrer dem i databasen
        foreach ($companies as $company):
            Company::createFromEnhetsregisterdata($company);
        endforeach;
    }
}
