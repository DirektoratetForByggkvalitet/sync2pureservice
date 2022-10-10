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

    protected JamfController $jpsApi;
    protected PureserviceController $psApi;

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
        $this->jpsApi = new JamfController();
        $this->psApi = new PureserviceController();
        $this->info('> Henter inn fra Pureservice');
        $psDevices = collect($this->psApi->getAllAssets());
        $this->line('  '.count($psDevices).' enheter');
        $this->info('> Henter inn fra Jamf Pro');
        $jamfDevices = collect($this->jpsApi->getJamfAssetsAsPsAssets());
        $this->line('  '.count($jamfDevices).' enheter');

        // Looper gjennom Jamf-enheter for å oppdatere eller legge dem til i Pureservice
        $this->info('> Starter behandling av enheter fra Jamf Pro');
        $itemno = 0;
        $count = count($jamfDevices);
        foreach ($jamfDevices as $dev):
            $itemno++;
            $this->line('   - '.$itemno.'/'.$count.' \''.$dev[config('pureservice.field_prefix').'Navn'].'\'');
            $uniqueField = config('pureservice.field_prefix').'Serienr';
            $psDev = $psDevices->firstWhere($uniqueField, $dev[$uniqueField]) || false;
            if ($psDev):
                $this->line('     Enheten finnes i Pureservice fra før. Oppdaterer med data fra Jamf Pro...');
                $this->psApi->updateAsset($dev);
            else:
                $this->line('     Enheten finnes ikke i Pureservice fra før. Legger til...');
                $this->psApi->createAsset($dev);
            endif;
        endforeach;

        // Looper gjennom Pureservice-enheter for å evt. endre status på enheter som ikke lenger finnes i Jamf Pro
        foreach ($psDevices as $dev):

        endforeach;
        return Command::SUCCESS;
    }


}
