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
    protected $l1 = '> ';
    protected $l2 = '   - ';
    protected $l3 = '     ';

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
        $this->info($this->l1.'Henter inn fra Pureservice');
        $psDevices = collect($this->psApi->getAllAssets());
        $this->line($this->l3.count($psDevices).' enheter');
        $this->info($this->l1.'Henter inn fra Jamf Pro');
        $jamfDevices = collect($this->jpsApi->getJamfAssetsAsPsAssets());
        $this->line($this->l3.count($jamfDevices).' enheter');

        // Looper gjennom Jamf-enheter for å oppdatere eller legge dem til i Pureservice
        $this->info($this->l1.'Starter behandling av enheter fra Jamf Pro');
        $itemno = 0;
        $count = count($jamfDevices);
        foreach ($jamfDevices as $dev):
            $itemno++;
            $this->line($this->l2.$itemno.'/'.$count.' \''.$dev[config('pureservice.field_prefix').'Navn'].'\'');
            $uniqueField = config('pureservice.field_prefix').'Serienr';
            $psDev = $psDevices->firstWhere($uniqueField, $dev[$uniqueField]) || false;
             if ($psDev):
                $this->line($this->l3.'Enheten finnes i Pureservice fra før. Oppdaterer med data fra Jamf Pro...');
                $this->psApi->updateAsset($dev, $psDev['id']);
            else:
                $this->line($this->l3.'Enheten finnes ikke i Pureservice fra før. Legger til...');
                if ($id = $this->psApi->createAsset($dev)):
                    $this->line($this->l3.'Lagt til med id='.$id);
                else:
                    $this->error($this->l3.'Det oppsto en feil under innlegging...');
                endif;
            endif;
            if (count($dev['usernames']) > 0):
                $doLink = true;
                if ($psDev):
                    // Fjerner eksisterende brukerkoblinger, hvis nødvendig
                    $psUsernames = $this->psApi->getRelatedUsernames($psDev['id']);
                    if (array_diff($psUsernames, $dev['usernames']) == []):
                        $doLink = false;
                    else:
                        // Fjerner eksisterende brukerkoblinger
                        $relationships = $this->psApi->getRelationships($psDev['id'])['relationships'];
                        foreach ($relationships as $link):
                            $uri = '/relationship/'.$link['id'].'/delete';
                            $this->psApi->apiDELETE($uri);
                        endforeach;
                    endif;
                endif;

                if ($doLink):
                    $this->line($this->l3.'Kobler enheten til brukerkontoen(e) '.implode(', ', $dev['usernames']));
                    if ($this->relateAssetToUsernames($id, $dev['usernames'])):
                        $this->line($this->l3.'Kobling fullført');
                    else:
                        $this->error($this->l3.'Koblingen mislyktes');
                    endif;
                else:
                    $this->line($this->l3.'Brukerkobling trenger ikke oppdatering');
                endif;
            else:
                $this->line($this->l3.'Enheten er ikke koblet til noen bruker');
            endif;
        endforeach;

        // Looper gjennom Pureservice-enheter for å evt. endre status på enheter som ikke lenger finnes i Jamf Pro
        $this->info($this->l1. 'Går gjennom enhetene i Pureservice for å oppdatere status for enheter fjernet fra Jamf Pro');
        $count = count($psDevices);
        $itemno = 0;
        foreach ($psDevices as $dev):
            $itemno++;
            $this->info($this->l2.$itemno.'/'.$count.': '.$dev['']);
        endforeach;
        return Command::SUCCESS;
    }


}
