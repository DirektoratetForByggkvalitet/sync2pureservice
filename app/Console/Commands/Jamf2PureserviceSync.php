<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Http\Controllers\{JamfController, PureserviceController};

class Jamf2PureserviceSync extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'jamf2pureservice:sync';

    protected $version = '1.0';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Henter maskiner og mobile enheter fra Jamf Pro og synkroniserer dem med Pureservice sine Assets';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('Jamf2Pureservice v'.$this->version);
        $this->line($this->description);
        $this->line('');
        $jpsApi = new JamfController();
        $psApi = new PureserviceController();
        $this->info('> Henter inn fra Pureservice');
        $psDevices = collect($psApi->getAllAssets());
        $this->line('  '.count($psDevices).' enheter');
        $this->info('> Henter inn fra Jamf Pro');
        $JamfDevices = $jpsApi->getJamfAssetsAsPsAssets();
        $this->line('  '.count($JamfDevices).' enheter');
        return Command::SUCCESS;
    }
}
