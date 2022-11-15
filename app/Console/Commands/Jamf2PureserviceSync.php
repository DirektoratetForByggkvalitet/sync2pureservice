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

    protected $version = '1.1.0';

    protected JamfController $jpsApi;
    protected PureserviceController $psApi;
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
     **/
    protected $description = 'Henter maskiner og mobile enheter fra Jamf Pro og synkroniserer dem med Pureservice sine Assets';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle() {
        $this->start = microtime(true);
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

        $this->line('');

        $this->info($this->ts().$this->l1.'Henter enheter fra Pureservice');
        $time1 = microtime(true);
        $this->psDevices = collect($this->psApi->getAllAssets());
        $this->psCount = count($this->psDevices);
        $this->line($this->l3.$this->psCount.' enheter totalt ('.round((microtime(true) - $time1), 2).' sek)');

        $time1 = microtime(true);
        $this->info($this->ts().$this->l1.'Henter enheter fra Jamf Pro');
        $this->jamfDevices = collect($this->getJamfAssetsAsPsAssets());
        $this->line($this->l3.$this->jamfCount.' enheter totalt ('.round((microtime(true) - $time1), 2).' sek)');
        unset($time1);

        // Looper gjennom Jamf-enheter for å oppdatere eller legge dem til i Pureservice
        $this->info($this->ts().$this->l1.'Starter behandling av enheter fra Jamf Pro');
        $itemno = 0;
        foreach ($this->jamfDevices as $jamfDev):
            $itemno++;
            $time1 = microtime(true);
            $fn = config('pureservice.'.$jamfDev['type'].'.properties');
            $this->line('');
            $this->line($this->l2.$itemno.'/'.$this->jamfCount.' '.$jamfDev[$fn['serial']].' - '.$jamfDev[$fn['name']]);
            $psDev = $this->psDevices->firstWhere('links.unique.id', $jamfDev[$fn['serial']]);
            $typeName = config('pureservice.'.$jamfDev['type'].'.displayName').'en';

            if ($psDev != null):
                $psDevId = $psDev['id'];
                $this->line($this->l3.$typeName.' finnes i Pureservice fra før. Oppdaterer med data fra Jamf Pro...');
                if ($ok = $this->psApi->updateAsset($jamfDev, $psDev)):
                    $this->line($this->l3.'  Oppdaterte '.$typeName.' i Pureservice');
                else:
                    $this->error($this->l3.'  Det oppsto en feil under oppdatering...');
                endif;

            else:
                $this->line($this->l3.$typeName.' finnes ikke i Pureservice fra før. Legger til...');
                if ($psDevId = $this->psApi->createAsset($jamfDev)):
                    $this->line($this->l3.'  Lagt til med id='.$psDevId);
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
                        $this->removeRelationships($psDevId);
                    endif;
                endif;

                if ($doLink):
                    $this->line($this->l3.'Kobler '.$typeName.' ('.$psDevId.') til brukerkontoen '.implode(', ', $jamfDev['usernames']));
                    if ($this->psApi->relateAssetToUsernames($psDevId, $jamfDev['usernames'], $jamfDev['type'])):
                        $this->line($this->l3.'  Kobling fullført');
                    else:
                        $this->info($this->l3.'  Ingen kobling, brukeren finnes ikke i Pureservice');
                    endif;
                else:
                    $this->line($this->l3.'Brukerkobling trenger ikke oppdatering');
                endif;
            else:
                $this->line($this->l3.'Enheten er ikke koblet til noen bruker');
            endif;
            $elapsed = microtime(true) - $time1;
            $this->line($this->l3.'Behandlingstid: '.round($elapsed, 2).' sek');
        endforeach;

        $this->line('');

        // Looper gjennom Pureservice-enheter for å evt. endre status på enheter som ikke lenger finnes i Jamf Pro
        $this->info($this->ts().$this->l1.'Oppdaterer status for enheter som eventuelt er fjernet fra Jamf Pro');

        foreach ($this->psDevices as $dev):
            $fn = config('pureservice.'.$dev['type'].'.properties');
            if (!$this->jamfDevices->contains($fn['serial'],$dev['uniqueId'])):
                $this->line($this->l2.$dev['uniqueId'].' - '.$dev[$fn['name']]);
                $typeName = config('pureservice.'.$dev['type'].'.displayName').'en';
                $this->line($this->l3.$typeName.' er ikke registrert i Jamf Pro');
                if (
                    $dev['statusId'] == config('pureservice.'.$dev['type'].'.status.active_deployed') ||
                    $dev['statusId'] == config('pureservice.'.$dev['type'].'.status.active_phaseOut') ||
                    $dev['statusId'] == config('pureservice.'.$dev['type'].'.status.active_inStorage')
                ):
                    if ($dev['usernames'] != []):
                        $this->line($this->l3.$typeName.' er registrert på '.implode(', ', $dev['usernames']));
                        $this->line($this->l3.'Fjerner brukerkobling(er)');
                        $this->removeRelationships($dev['id']);
                    endif;
                    $this->line($this->l3.'Endrer status på enheten i Pureservice');
                    $newStatusId = $this->psApi->calculateStatus($dev, true);
                    if ($this->psApi->updateAssetDetail($dev['id'], ['statusId' => $newStatusId])):
                        $this->line($this->l3.'Status oppdatert');
                    else:
                        $this->error($this->l3.'Fikk ikke endret status');
                    endif;
                    $this->line('');
                endif;
                if ($dev[$fn['jamfUrl']] != null):
                    if ($this->psApi->updateAssetDetail($dev['id'], [$fn['jamfUrl'] => null])):
                        $this->line($this->l3.'Tok vekk Jamf-URL');
                    else:
                        $this->line($this->l3.'Fikk ikke fjernet Jamf-URL');
                    endif;
                endif;
            endif;
        endforeach;

        // Oppsummering
        $this->line('');
        $this->info($this->ts().'Synkronisering ferdig');
        $this->line($this->l3.$this->jamfCount.' enheter fra Jamf Pro ble synkroniserte med '.$this->psCount.' enheter i Pureservice.');
        $this->line($this->l3.'Operasjonen ble fullført på '.round(microtime(true) - $this->start, 2).' sekunder');

        return Command::SUCCESS;
    }

    protected function ts() {
        return '['.Carbon::now(config('app.timezone'))->toDateTimeLocalString().'] ';
    }

    protected function removeRelationships($assetId) {
        $relationships = $this->psApi->getRelationships($assetId)['relationships'];
        foreach ($relationships as $rel):
            $uri = '/relationship/'.$rel['id'].'/delete';
            $this->psApi->apiDelete($uri);
        endforeach;
        return true;
    }

    public function getJamfAssetsAsPsAssets() {
        $psAssets = [];
        $computers = $this->jpsApi->getJamfComputers();
        $this->line($this->l3.count($computers).' datamaskiner');
        foreach ($computers as $mac):
            // Skipper enheten hvis den ikke har serienummer
            if ($mac['hardware']['serialNumber'] == null || $mac['hardware']['serialNumber'] == '') continue;

            $fn = config('pureservice.computer.properties'); // strukturerte feltnavn
            $psAsset = [];
            $psAsset[$fn['name']] = $mac['general']['name'] != '' ? $mac['general']['name'] : '-uten-navn-';
            $psAsset[$fn['serial']] = $mac['hardware']['serialNumber'];
            $psAsset[$fn['model']] = $mac['hardware']['model'];
            $psAsset[$fn['modelId']] = $mac['hardware']['modelIdentifier'];
            if ($mac['hardware']['processorType'] != null):
                $psAsset[$fn['processor']] = $mac['hardware']['processorType'];
            endif;
            if ($mac['operatingSystem']['version'] != null):
                $psAsset[$fn['OsVersion']] = $mac['operatingSystem']['version'];
            endif;

            $psAsset[$fn['memberSince']] = Carbon::create($mac['general']['initialEntryDate'])
                ->timezone(config('app.timezone'))
                ->toJSON();
            $psAsset[$fn['EOL']] = Carbon::create($mac['general']['initialEntryDate'])
                ->timezone(config('app.timezone'))
                ->addYears(config('pureservice.computer.lifespan', 4))
                ->toJSON();
            if ($mac['general']['lastContactTime'] != null):
                $psAsset[$fn['lastSeen']] = Carbon::create($mac['general']['lastContactTime'])
                    ->timezone(config('app.timezone'))
                    ->toJSON();
            endif;

            $psAsset[$fn['jamfUrl']] = config('jamfpro.api_url').'/computers.html?id='.$mac['id'].'&o=r';

            $psAsset['usernames'] = [];
            if ($mac['userAndLocation']['username'] != null) $psAsset['usernames'][] = $mac['userAndLocation']['username'];
            $psAsset['type'] = 'computer';
            $psAssets[] = $psAsset;
            unset($psAsset);
        endforeach;
        unset($computers);

        $devices = $this->jpsApi->getJamfMobileDevices();
        $this->line($this->l3.count($devices).' mobilenheter');
        foreach ($devices as $dev):
            // Skipper enheten hvis den ikke har serienummer
            if ($dev['serialNumber'] == null || $dev['serialNumber'] == '') continue;

            $psAsset = [];
            $psAsset[$fn['name']] = $dev['name'] == '' ? '-uten-navn-': $dev['name'];
            $psAsset[$fn['serial']] = $dev['serialNumber'];
            $psAsset[$fn['model']] = $dev[$dev['type']]['model'];
            $psAsset[$fn['modelId']] = $dev[$dev['type']]['modelIdentifier'];
            if ($dev['osVersion'] != null):
                $psAsset[$fn['OsVersion']] = $dev['osVersion'];
            endif;

            $psAsset[$fn['memberSince']] = Carbon::create($dev['initialEntryTimestamp'])
                ->timezone(config('app.timezone'))
                ->toJSON();
            $psAsset[$fn['EOL']] = Carbon::create($dev['initialEntryTimestamp'])
                ->timezone(config('app.timezone'))
                ->addYears(config('pureservice.mobile.lifespan', 3))
                ->toJSON();
            if ($dev['lastInventoryUpdateTimestamp'] != null):
                $psAsset[$fn['lastSeen']] = Carbon::create($dev['lastInventoryUpdateTimestamp'])
                    ->timezone(config('app.timezone'))
                    ->toJSON();
            endif;

            $psAsset[$fn['jamfUrl']] = config('jamfpro.api_url').'/mobileDevices.html?id='.$dev['id'].'&o=r';

            $psAsset['usernames'] = [];
            if ($dev['location']['username'] != null) $psAsset['usernames'][] = $dev['location']['username'];
            $psAsset['type'] = 'mobile';
            $psAssets[] = $psAsset;
            unset($psAsset);
        endforeach;
        unset($devices);
        $this->jamfCount = count($psAssets);
        return $psAssets;
    }
}
