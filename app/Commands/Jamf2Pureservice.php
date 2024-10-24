<?php

namespace App\Commands;

use Illuminate\Console\Scheduling\Schedule;
use LaravelZero\Framework\Commands\Command;
use App\Services\{JamfPro, PsAssets, Tools};
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\{Arr, Collection, Str};


class Jamf2Pureservice extends Command {
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'pureservice:sync-jamf';

    protected $version = '2.2';

    protected JamfPro $jamfPro;
    protected PsAssets $psApi;
    protected $jamfDevices;
    protected $psDevices;
    protected $updatedPsDevices = [];
    protected $start;
    protected $jamfCount;
    protected $psCount;
    protected $psOnlyCount;
    protected $deleteCount = 0;

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
        $this->newline();

        $this->line(Tools::l2().'Logger inn på Jamf Pro');
        $this->jamfPro = new JamfPro();
        if ($this->jamfPro->up):
            $this->line(Tools::L3.'Jamf Pro er tilkoblet og klar til bruk');
            $this->line(Tools::L3.'Bruker URL '.config('jamfpro.api.url').config('jamfpro.api.prefix'));
        else:
            $this->error(Tools::L3.'Jamf Pro er nede eller feilkonfigurert');
            dd(config('jamfpro.api'));
            return Command::FAILURE;
        endif;

        $this->newLine();

        $this->line(Tools::L2.'Logger inn på Pureservice');
        $this->psApi = new PsAssets();
        if ($this->psApi->up):
            $this->line(Tools::L3.'Pureservice er tilkoblet og svarer normalt');
            $this->line(Tools::L3.'Bruker URL '.config('pureservice.api.url').config('pureservice.api.prefix'));
        else:
            $this->error(Tools::L3.'Pureservice er nede eller feilkonfigurert');
            return Command::FAILURE;
        endif;

        $this->newLine();


        $time1 = microtime(true);
        $this->info(Tools::L1.'1. Henter enheter fra Jamf Pro');
        $this->jamfDevices = Cache::remember('jamfDevices', 3600, function() {
            return collect($this->getJamfAssetsAsPsAssets());
        });
        $this->jamfCount = $this->jamfDevices->count();
        $this->line(Tools::L3.$this->jamfCount.' enheter totalt ('.round((microtime(true) - $time1), 2).' sek)');

        $this->newLine();

        $time1 = microtime(true);
        $this->info(Tools::L1.'2. Henter enheter fra Pureservice');
        $this->psDevices = Cache::remember('psDevices', 3600, function() {
            return $this->psApi->getAllAssets();
        });
        $this->psCount = count($this->psDevices);
        $this->line(Tools::L3.$this->psCount.' enheter totalt ('.round((microtime(true) - $time1), 2).' sek)');
        unset($time1);
        $this->newLine();


        // Looper gjennom Jamf-enheter for å oppdatere eller legge dem til i Pureservice
        $this->info(Tools::L1.'3. Starter behandling av enheter fra Jamf Pro');
        $itemno = 0;
        foreach ($this->jamfDevices->lazy() as $jamfDev):
            $itemno++;
            $time1 = microtime(true);
            $fn = config('pureservice.'.$jamfDev['type'].'.properties');
            $this->newLine();
            $this->line(Tools::L2.$itemno.'/'.$this->jamfCount.' '.$jamfDev[$fn['serial']].' - '.$jamfDev[$fn['name']]);
            $psDev = $this->psDevices->firstWhere('uniqueId', $jamfDev[$fn['serial']]);
            // Sjekker om enheten finnes i Pureservice
            //$psDev = $this->psApi->getAssetByUniqueId($jamfDev[$fn['serial']], $jamfDev['type']);
            //dd($psDev);
            $typeName = config('pureservice.'.$jamfDev['type'].'.displayName').'en';

            if ($psDev):
                $psDevId = $psDev['id'];
                $this->line(Tools::L3.$typeName.' finnes i Pureservice fra før.');
                $statusId = $this->psApi->calculateStatus($psDev);
                $jamfAsset = array_merge($psDev, $jamfDev);
                $updateAsset = collect($jamfAsset)
                    ->except([
                        'usernames', 'type',
                        'modified', 'modifiedById',
                        'created', 'createdById',
                        'importedById', 'importJobId',
                        'restrictedDepartmentId',
                        'restrictedTeamId',
                        'restrictedUserId',
                        'links',
                        'isMarkedForDeletion',
                        'id',
                    ])->toArray();
                $updateAsset['statusId'] = $statusId;
                $psAsset = collect($psDev)
                    ->except([
                        'usernames', 'type',
                        'modified', 'modifiedById',
                        'created', 'createdById',
                        'importedById', 'importJobId',
                        'restrictedDepartmentId',
                        'restrictedTeamId',
                        'restrictedUserId',
                        'links',
                        'isMarkedForDeletion',
                        'id',
                    ])->toArray();

                $diff = array_diff_assoc($updateAsset, $psAsset);
                if ($diff === []):
                    $this->line(Tools::L3.'Oppdatering ikke nødvendig.');
                else:
                    $this->line(Tools::L3.'Oppdaterer med data fra Jamf Pro.');
                    $result = $this->psApi->updateAssetDetail($jamfAsset, $diff);
                endif;

            else:
                $this->line(Tools::L3.$typeName.' finnes ikke i Pureservice fra før. Legger til...');
                if ($psDevId = $this->psApi->createAsset($jamfDev)):
                    $this->line(Tools::L3.'  Lagt til med id='.$psDevId);
                else:
                    $this->error(Tools::L3.'Det oppsto en feil under innlegging...');
                endif;
            endif;
            if (count($jamfDev['usernames']) > 0):
                $doLink = true;
                if ($psDev):
                    $psUsernames = array_unique($this->psApi->getAssetRelatedUsernames($psDevId));
                    $jamfDev['usernames'] = array_unique($jamfDev['usernames']);
                    if ($jamfDev['usernames'] === $psUsernames):
                        $doLink = false;
                    else:
                        // Fjerner eksisterende brukerkoblinger før ny kobling
                        $this->line(Tools::L3.'Fjerner brukerkoblinger til enheten');
                        $this->removeRelationships($psDevId);
                    endif;
                endif;

                if ($doLink):
                    $this->line(Tools::L3.'Kobler '.Str::lower($typeName).' ('.$psDevId.') til brukerkontoen '.implode(', ', $jamfDev['usernames']));
                    if ($this->psApi->relateAssetToUsernames($psDevId, $jamfDev['usernames'], $jamfDev['type'])):
                        $this->line(Tools::L3.'  Kobling fullført');
                    else:
                        $this->info(Tools::L3.'  Ingen kobling, kanskje brukeren ikke finnes i Pureservice?');
                    endif;
                else:
                    $this->line(Tools::L3.'Brukerkobling trenger ikke oppdatering');
                endif;
            else:
                $this->line(Tools::L3.'Enheten er ikke koblet til noen bruker');
            endif;
            $this->updatedPsDevices[] = $psDevId;
            $elapsed = microtime(true) - $time1;
            $this->line(Tools::L3.'Behandlingstid: '.round($elapsed, 2).' sek');
        endforeach;
        $this->newLine();
        // Looper gjennom Pureservice-enheter for å evt. endre status på enheter som ikke lenger finnes i Jamf Pro
        $notUpdatedDevs = $this->psDevices->lazy()->whereNotIn('id', $this->updatedPsDevices);
        $this->psOnlyCount = $notUpdatedDevs->count();
        $this->info(Tools::L1.'4. Oppdaterer status for '.$this->psOnlyCount.' enheter som ikke er i Jamf Pro');
        $i = 0;
        foreach ($notUpdatedDevs as $dev):
            $i++;
            $fn = config('pureservice.'.$dev['type'].'.properties');
            $this->line(Tools::L2.$i.'/'.$this->psOnlyCount.' '.$dev['uniqueId'].' - '.$dev[$fn['name']]);
            $typeName = config('pureservice.'.$dev['type'].'.displayName').'en';
            //$this->line(Tools::L3.$typeName.' er ikke registrert i Jamf Pro');
            $updated = false;
            $cutoff = Carbon::now()->subYears(1);
            if ($dev[$fn['lastSeen']] != null):
                $devLastSeen = Carbon::parse($dev[$fn['lastSeen']], 'Europe/Oslo');
                $eolDiff = $devLastSeen->diffInDays($cutoff, false);
                // Sletter enheten dersom den ble sist sett for mer enn ett år siden
                if ($eolDiff >= 0):
                    $this->line(Tools::L3.'Enheten ble sist sett '.$devLastSeen->format('d.m.Y H:i').'. Sletter den fra Pureservice.');
                    $this->psApi->deleteAsset($dev);
                    $this->deleteCount++;
                    $this->newLine();
                    continue;
                endif;
            endif;

            if (
                $dev['statusId'] == config('pureservice.'.$dev['type'].'.status.active_deployed') ||
                $dev['statusId'] == config('pureservice.'.$dev['type'].'.status.active_phaseOut') ||
                $dev['statusId'] == config('pureservice.'.$dev['type'].'.status.active_inStorage')
            ):
                if ($dev['usernames'] != []):
                    $this->line(Tools::L3.$typeName.' er registrert på '.implode(', ', $dev['usernames']));
                    $this->line(Tools::L3.'Fjerner brukerkobling(er)');
                    $this->removeRelationships($dev['id']);
                endif;
            endif;
            $newStatusId = $this->psApi->calculateStatus($dev, true);
            if ($dev['statusId'] != $newStatusId):
                $this->psApi->changeAssetStatus($dev, $newStatusId);
                $this->line(Tools::L3.'Endret status på enheten');
                $updated = true;
            endif;
            if ($dev[$fn['jamfId']] != null):
                $this->psApi->updateAssetDetail($dev, [$fn['jamfId'] => null,]);
                $this->line(Tools::L3.'Fjernet lenke til Jamf Pro');
                $updated = true;
            endif;
            if (!$updated) $this->line(Tools::L3.'Enheten trenger ikke oppdatering i Pureservice');
            $this->newLine();
        endforeach;

        // Oppsummering
        $this->newLine();
        $this->info(Tools::ts().'Synkronisering ferdig');
        $this->line(Tools::L2.$this->jamfCount.' enheter fra Jamf Pro ble oppdaterte i Pureservice.');
        $this->line(Tools::L2.'Behandlet '.$this->psOnlyCount.' enheter i Pureservice som ikke ligger i Jamf Pro.');
        if ($this->deleteCount) $this->line(Tools::L3.$this->deleteCount . ' av disse ble slettet.');
        $this->line(Tools::L2.class_basename($this).' fullførte på '.round(microtime(true) - $this->start, 2).' sekunder');

        return Command::SUCCESS;

    }

    protected function removeRelationships($assetId) {
        $relationships = $this->psApi->getAssetRelationships($assetId)['relationships'];
        foreach ($relationships as $rel):
            $uri = '/relationship/'.$rel['id'];
            $this->psApi->apiDelete($uri);
        endforeach;
        return true;
    }


    public function getJamfAssetsAsPsAssets() {
        $psAssets = [];

        $devices = $this->jamfPro->getMobileDevices();
        $fn = config('pureservice.mobile.properties');
        //$this->line(Tools::L3.count($devices).' mobilenheter');
        foreach ($devices as $dev):
            // Skipper enheten hvis den ikke har serienummer
            if ($dev['hardware']['serialNumber'] == null || $dev['hardware']['serialNumber'] == '') continue;

            $psAsset = [];
            $psAsset[$fn['name']] = $dev['general']['displayName'] == '' ? '-uten-navn-': $dev['general']['displayName'];
            $psAsset['name'] = $psAsset[$fn['name']];
            $psAsset[$fn['serial']] = $dev['hardware']['serialNumber'];
            $psAsset[$fn['model']] = $dev['hardware']['model'];
            $psAsset[$fn['modelId']] = $dev['hardware']['modelIdentifier'];
            if ($dev['general']['osVersion'] != null):
                $psAsset[$fn['OsVersion']] = $dev['general']['osVersion'];
            endif;
            if (isset($dev['general']['initialEntryTimestamp']) && $dev['general']['initialEntryTimestamp'] != ''):
                $memberSinceData = &$dev['general']['initialEntryTimestamp'];
             else:
                $memberSinceData = &$dev['general']['lastEnrolledDate'];
            endif;
            $memberSince = Carbon::create($memberSinceData)->timezone(config('app.timezone'));

            $psAsset[$fn['memberSince']] = $memberSince->copy()->toJSON();
            $psAsset[$fn['EOL']] = $memberSince->copy()
                ->addYears(config('pureservice.mobile.lifespan', 3))
                ->toJSON();
            unset($memberSince);
            if ($dev['general']['lastInventoryUpdateDate'] != null):
                $psAsset[$fn['lastSeen']] = Carbon::create($dev['general']['lastInventoryUpdateDate'])
                    ->timezone(config('app.timezone'))
                    ->toJSON();
            endif;

            $psAsset[$fn['jamfId']] = $dev['mobileDeviceId'];
            $extAttributes = collect($dev['extensionAttributes']);
            $deviceRisk = $extAttributes->firstWhere('name', 'wandera_device_risk');
            $psAsset[$fn['riskAssessment']] = isset($deviceRisk['value'][0])? $deviceRisk['value'][0]: '';

            $psAsset['usernames'] = [];
            if ($dev['userAndLocation']['username'] != null) $psAsset['usernames'][] = $dev['userAndLocation']['username'];
            $psAsset['type'] = 'mobile';
            $psAssets[] = $psAsset;
            unset($psAsset);
        endforeach;
        unset($devices);

        $computers = $this->jamfPro->getComputers();
        $fn = config('pureservice.computer.properties'); // strukturerte feltnavn
        //$this->line(Tools::L3.count($computers).' datamaskiner');
        foreach ($computers as $mac):
            // Skipper enheten hvis den ikke har serienummer
            if ($mac['hardware']['serialNumber'] == null || $mac['hardware']['serialNumber'] == '') continue;

            $psAsset = [];
            $psAsset[$fn['name']] = $mac['general']['name'] != '' ? $mac['general']['name'] : '-uten-navn-';
            $psAsset['name'] = $psAsset[$fn['name']];
            $psAsset[$fn['serial']] = $mac['hardware']['serialNumber'];
            $psAsset[$fn['model']] = $mac['hardware']['model'];
            $psAsset[$fn['modelId']] = $mac['hardware']['modelIdentifier'];
            if ($mac['hardware']['processorType'] != null):
                $psAsset[$fn['processor']] = $mac['hardware']['processorType'];
            endif;
            if ($mac['operatingSystem']['version'] != null):
                $psAsset[$fn['OsVersion']] = $mac['operatingSystem']['version'];
            endif;

            if (isset($mac['general']['initialEntryDate']) && $mac['general']['initialEntryDate'] != ''):
                $memberSinceData = &$mac['general']['initialEntryDate'];
             else:
                $memberSinceData = &$mac['general']['lastEnrolledDate'];
            endif;
            $memberSince = Carbon::create($memberSinceData)->timezone(config('app.timezone'));

            $psAsset[$fn['memberSince']] = $memberSince->copy()->toJSON();
            $psAsset[$fn['EOL']] = $memberSince->copy()
                ->addYears(config('pureservice.computer.lifespan', 4))
                ->toJSON();
            if ($mac['general']['lastContactTime'] != null):
                $psAsset[$fn['lastSeen']] = Carbon::create($mac['general']['lastContactTime'])
                    ->timezone(config('app.timezone'))
                    ->toJSON();
            endif;
            unset($memberSince);
            $psAsset[$fn['jamfId']] = $mac['id'];
            $extAttributes = collect($mac['extensionAttributes']);
            $deviceRisk = $extAttributes->firstWhere('name', 'wandera_device_risk');
            $psAsset[$fn['riskAssessment']] = isset($deviceRisk['value'][0])? $deviceRisk['value'][0]: '';

            $psAsset['usernames'] = [];
            if ($mac['userAndLocation']['username'] != null) $psAsset['usernames'][] = $mac['userAndLocation']['username'];
            $psAsset['type'] = 'computer';
            $psAssets[] = $psAsset;
            unset($psAsset);
        endforeach;
        unset($computers);

        return $psAssets;
    }

}
