<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Http\Controllers\{JamfController, PureserviceController};
use Carbon\Carbon;

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
    protected $jamfDevices;
    protected $psDevices;
    protected $l1 = '';
    protected $l2 = '> ';
    protected $l3 = '  ';

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

        $this->line($this->l2.'Logger inn på Pureservice');
        $this->psApi = new PureserviceController();
        if ($this->psApi->up):
            $this->line($this->l3.'Pureservice er tilkoblet og svarer normalt');
        else:
            $this->error($this->l3.'Pureservice er nede eller feilkonfigurert');
            return Command::FAILURE;
        endif;

        $this->line($this->l2.'Logger inn på Jamf Pro');
        $this->jpsApi = new JamfController();
        if ($this->jpsApi->up):
            $this->line($this->l3.'Jamf Pro er tilkoblet og svarer normalt');
        else:
            $this->error($this->l3.'Jamf Pro er nede eller feilkonfigurert');
            return Command::FAILURE;
        endif;


        $this->info($this->ts().$this->l1.'Henter inn fra Pureservice');
        $psDevices = collect($this->psApi->getAllAssets());
        $this->line($this->l3.count($psDevices).' enheter');

        $this->info($this->ts().$this->l1.'Henter inn fra Jamf Pro');
        $jamfDevices = collect($this->jpsApi->getJamfAssetsAsPsAssets());
        $this->line($this->l3.count($jamfDevices).' enheter');

        // Looper gjennom Jamf-enheter for å oppdatere eller legge dem til i Pureservice
        $this->info($this->ts().$this->l1.'Starter behandling av enheter fra Jamf Pro');
        $itemno = 0;
        $count = count($jamfDevices);
        foreach ($jamfDevices as $jamfDev):
            $itemno++;
            $time1 = microtime(true);
            $this->line($this->l2.$itemno.'/'.$count.' \''.$jamfDev[config('pureservice.field_prefix').'Navn'].'\'');
            $uniqueField = config('pureservice.field_prefix').'Serienr';
            $psDev = $psDevices->firstWhere($uniqueField, $jamfDev[$uniqueField]);

            if ($psDev != null):
                $psDevId = $psDev['id'];
                $this->line($this->l3.'Enheten finnes i Pureservice fra før. Oppdaterer med data fra Jamf Pro...');
                if ($ok = $this->psApi->updateAsset($jamfDev, $psDev)):
                    $this->line($this->l3.'Oppdaterte enheten med id='.$psDevId.' i Pureservice');
                else:
                    $this->error($this->l3.'Det oppsto en feil under oppdatering...');
                endif;

            else:
                $this->line($this->l3.'Enheten finnes ikke i Pureservice fra før. Legger til...');
                if ($psDevId = $this->psApi->createAsset($jamfDev)):
                    $this->line($this->l3.'Lagt til med id='.$psDevId);
                else:
                    $this->error($this->l3.'Det oppsto en feil under innlegging...');
                endif;
            endif;
            if (count($jamfDev['usernames']) > 0):
                $doLink = true;
                if ($psDev):
                    $psUsernames = $this->psApi->getRelatedUsernames($psDevId);
                    if ($psUsernames === $jamfDev['usernames']):
                        $doLink = false;
                    else:
                        // Fjerner eksisterende brukerkoblinger
                        $this->line($this->l3.'Fjerner brukerkoblinger til enheten');
                    endif;
                endif;

                if ($doLink):
                    $this->line($this->l3.'Kobler enheten ('.$psDevId.') til brukerkontoen(e) '.implode(', ', $jamfDev['usernames']));
                    if ($this->psApi->relateAssetToUsernames($psDevId, $jamfDev['usernames'])):
                        $this->line($this->l3.'Kobling fullført');
                    else:
                        $this->error($this->l3.'Ingen kobling, brukeren finnes ikke i Pureservice');
                    endif;
                else:
                    $this->line($this->l3.'Brukerkobling trenger ikke oppdatering');
                endif;
            else:
                $this->line($this->l3.'Enheten er ikke koblet til noen bruker');
            endif;
            $elapsed = microtime(true) - $time1;
            $this->line($this->l3.'Operasjonen tok '.round($elapsed, 2).' sekunder');
        endforeach;

        // Looper gjennom Pureservice-enheter for å evt. endre status på enheter som ikke lenger finnes i Jamf Pro
        $this->info($this->ts().$this->l1. 'Går gjennom enhetene i Pureservice for å oppdatere status for enheter fjernet fra Jamf Pro');
        $count = count($psDevices);
        $itemno = 0;
        $fp = config('pureservice.field_prefix');
        $jamfCollection = collect($jamfDevices);
        foreach ($psDevices as $dev):
            $itemno++;
            $this->line($this->l2.$itemno.'/'.$count.': '.$dev[$fp.'Navn']);
            $existsInJamf = ($jamfCollection->firstWhere($fp.'Serienr', $dev[$fp.'Serienr'])) ? true : false;
            if (!$existsInJamf):
                if ($dev['statusId'] == config('pureservice.status.active_deployed')):
                    $this->line($this->l3.'Enheten er ikke i Jamf, men er merket som tildelt til bruker.');
                    if ($dev['usernames'] != []):
                        $this->line($this->l3.'Enheten er registrert på '.implode(', ', $dev['usernames']));
                        $this->line($this->l3.'Fjerner brukerkobling(er)');
                        $this->removeRelationships($dev['id']);
                    endif;
                    $this->line($this->l3.'Endrer status på enheten i Pureservice');
                    $newStatusId = $this->psApi->calculateStatus($dev, true);
                    if ($this->psApi->updateAssetStatus($dev['id'], $newStatusId)):
                        $this->line($this->l3.'Status oppdatert');
                    else:
                        $this->error($this->l3.'Fikk ikke endret status');
                    endif;
                endif;
            endif;
        endforeach;
        return Command::SUCCESS;
    }

    protected function ts() {
        return '['.Carbon::now(config('app.timezone'))->toDateTimeLocalString().'] ';
    }

    protected function removeRelationships($assetId) {
        $relationships = $this->psApi->getRelationships($assetId)['relationships'];
        foreach ($relationships as $rel):
            $uri = '/relationship/'.$rel['id'].'/delete';
            $this->psApi->apiDELETE($uri);
        endforeach;
        return true;
    }
}
