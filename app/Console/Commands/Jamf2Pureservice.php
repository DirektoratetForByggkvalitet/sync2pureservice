<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\{JamfPro, PSApi};
use Carbon\Carbon;


class Jamf2Pureservice extends Command {
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'pureservice:sync-jamf';

    protected $version = '2.0';

    protected JamfPro $jpsApi;
    protected PsApi $psApi;
    protected $jamfDevices;
    protected $psDevices;
    protected $l1 = '';
    protected $l2 = '> ';
    protected $l3 = '  ';
    protected $start;
    protected $jamfCount;
    protected $psCount;

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Henter maskiner og mobile enheter fra Jamf Pro og synkroniserer dem med Pureservice sine Assets';

    /**
     * Execute the console command.
     */
    public function handle() {
        $this->start = microtime(true);
        $this->info(class_basename($this).' v'.$this->version);
        $this->line($this->description);
        $this->newLine();

        $this->line($this->l2.'Logger inn på Jamf Pro');
        $this->jpsApi = new JamfPro();
        if ($this->jpsApi->up):
            $this->line($this->l3.'Jamf Pro er tilkoblet og svarer normalt');
        else:
            $this->error($this->l3.'Jamf Pro er nede eller feilkonfigurert');
            return Command::FAILURE;
        endif;

        $this->line($this->l2.'Logger inn på Pureservice');
        $this->psApi = new PsApi();
        if ($this->psApi->up):
            $this->line($this->l3.'Pureservice er tilkoblet og svarer normalt');
        else:
            $this->error($this->l3.'Pureservice er nede eller feilkonfigurert');
            return Command::FAILURE;
        endif;


        // Oppsummering
        $this->line('');
        $this->info($this->ts().'Synkronisering ferdig');
        $this->line($this->l3.$this->jamfCount.' enheter fra Jamf Pro ble synkroniserte med '.$this->psCount.' enheter i Pureservice.');
        $this->line($this->l3.'Operasjonen ble fullført på '.round(microtime(true) - $this->start, 2).' sekunder');

        return Command::SUCCESS;

    }
}
