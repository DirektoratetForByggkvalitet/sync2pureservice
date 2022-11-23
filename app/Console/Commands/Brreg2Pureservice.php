<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class Brreg2Pureservice extends Command {

    protected $version = '1.0';
    protected $l1 = '';
    protected $l2 = '> ';
    protected $l3 = '  ';
    protected $start;
    protected $orgsToSync = [];
    protected $orgTypeSync;
    protected $subOrgSync;
     /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'brreg2pureservice:synk';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Synkroniserer gitte organisasjonstyper eller foretak som er undeliggende gitte foretak med Pureservice';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->start = microtime(true);
        $this->info((new \ReflectionClass($this))->getShortName().' v'.$this->version);
        $this->line($this->description);

        $this->info($this->ts().'Setter opp miljøet...');
        $this->checkConfig();
        $this->line('');

        return Command::SUCCESS;
    }

    /**
     * Sjekker at nødvendig konfigurasjon for synkronisering er satt
     */
    protected function checkConfig() {
        $this->line($this->l2.'API-URL for BRREG er satt til '.config('brreg.url'));
        if (config('brreg.orgtyper') == []):
            $this->orgTypeSync = false;
            $this->line($this->l2.'Slår av synkronisering av organisasjonstyper');
            $this->line($this->l3.'Settes av miljøvariabelen \'BRREG_SYNK_ORGTYPER\'');
        else:
            $this->orgTypeSync = true;
        endif;

        if (config('brreg.underliggende') == []):
            $this->subOrgSync = false;
            $this->line($this->l2.'Slår av synkronisering av underliggende organisasjoner');
            $this->line($this->l3.'Settes av miljøvariabelen \'BRREG_SYNK_UNDERORDNET\'');
        else:
            $this->subOrgSync = true;
        endif;
    }
}
