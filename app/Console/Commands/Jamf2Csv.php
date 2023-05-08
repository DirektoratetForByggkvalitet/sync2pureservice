<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\{JamfPro};
use Illuminate\Support\{Str, Arr};

class Jamf2Csv extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:jamf2csv';

    protected $version = "1.0";
    protected JamfPro $jps;
    protected $l1 = '';
    protected $l2 = '> ';
    protected $l3 = '  ';
    protected $start;
    protected $csvFile;

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Henter maskiner og mobilenheter fra Jamf Pro og lagrer dem som CSV-fil';

    /**
     * Execute the console command.
     */
    public function handle() : int {
        $this->start = microtime(true);
        $this->info(class_basename($this).' v'.$this->version);
        $this->line($this->description);
        $this->newLine();

        $this->line($this->l2.'Logger inn på Jamf Pro');
        $this->jps = new JamfPro();
        if ($this->jps->up):
            $this->line($this->l3.'Jamf Pro er tilkoblet og svarer normalt');
        else:
            $this->error($this->l3.'Jamf Pro er nede eller feilkonfigurert');
            return Command::FAILURE;
        endif;

        $this->line('');

        $this->csvFile = base_path('devices.csv');

        $devices = $this->getJamfDevices();
        if ($this->toCsv($devices)):
            $this->line($this->l1.'Eksporterte '.count($devices).' enheter til csv-filen \''.$this->csvFile.'\'');
            return Command::SUCCESS;
        else:
            $this->error('Noe gikk galt.');
            return Command::FAILURE;
        endif;
    }

    protected function getJamfDevices() : array {
        $results = [];
        // Header
        $csvLine = [];
        $csvLine['email'] = 'email';
        $csvLine['first_name'] = 'first_name';
        $csvLine['last_name'] = 'last_name';
        $csvLine['phone_number'] = 'phone_number';
        $csvLine['address'] = 'address';
        $csvLine['city'] = 'city';
        $csvLine['postal_code'] = 'postal_code';
        $csvLine['country'] = 'country';
        $csvLine['employee_number'] = 'employee_number';
        $csvLine['cost_centre'] = 'cost_centre';
        $csvLine['job_title'] = 'job_title';
        $csvLine['start_date'] = 'start_date';
        $csvLine['location_id'] = 'location_id';
        $csvLine['budget_id'] = 'budget_id';
        $csvLine['date_of_birth'] = 'date_of_birth';
        $csvLine['send_invitation'] = 'send_invitation';

        $csvLine['device_name'] = 'device_name';
        $csvLine['serial_number'] = 'serial_number';
        $csvLine['imei'] = 'imei';
        $csvLine['category'] = 'category';

        $csvLine['payor'] = 'payor';
        $csvLine['distributor_name'] = 'distributor_name';
        $csvLine['distributor_order_id'] = 'distributor_order_id';

        $csvLine['notes'] = 'notes';
        $csvLine['manager_notes'] = 'manager_notes';
        $csvLine['purchased_at'] = 'purchased_at';
        $csvLine['expires_at'] = 'expires_at';

        $results[] = $csvLine;

        $devices = $this->jps->getJamfComputers();
        foreach ($devices as $mac):
            // Skipper enheten hvis den ikke har serienummer eller brukernavn
            if ($mac['hardware']['serialNumber'] == null || $mac['hardware']['serialNumber'] == '') continue;
            if ($mac['userAndLocation']['username'] == null || $mac['userAndLocation']['username'] != '') continue;

            $csvLine = [];
            $csvLine['email'] = $mac['userAndLocation']['username'];
            // Tomme felter
            $csvLine['first_name'] = Str::beforeLast($mac['userAndLocation']['fullName'], ' ');
            $csvLine['last_name'] = Str::afterLast($mac['userAndLocation']['fullName'], ' ');
            $csvLine['phone_number'] = null;
            $csvLine['address'] = null;
            $csvLine['city'] = null;
            $csvLine['postal_code'] = null;
            $csvLine['country'] = null;
            $csvLine['employee_number'] = null;
            $csvLine['cost_centre'] = null;
            $csvLine['job_title'] = null;
            $csvLine['start_date'] = null;
            $csvLine['location_id'] = null;
            $csvLine['budget_id'] = null;
            $csvLine['date_of_birth'] = null;
            $csvLine['send_invitation'] = null;

            $csvLine['device_name'] = $mac['general']['name'] != '' ? $mac['general']['name'] : '-uten-navn-';
            $csvLine['serial_number'] = $mac['hardware']['serialNumber'];
            $csvLine['imei'] = null;
            $csvLine['category'] = 'computers';

            $csvLine['payor'] = null;
            $csvLine['distributor_name'] = null;
            $csvLine['distributor_order_id'] = null;

            $csvLine['notes'] = null;
            $csvLine['manager_notes'] = 'Importert fra Jamf Pro';
            $csvLine['purchased_at'] = null;
            $csvLine['expires_at'] = null;

            $results[] = $csvLine;
        endforeach;
        unset($devices);
        $devices = $this->jps->getJamfMobileDevices(false);

        foreach ($devices as $dev):
            // Skipper enheten hvis den ikke har serienummer eller brukernavn
            if ($dev['serialNumber'] == null || $dev['serialNumber'] == '') continue;
            if ($dev['username'] == null || $dev['username'] == '') continue;

            $csvLine = [];
            $csvLine['email'] = $dev['username'];
            // Tomme felter
            $csvLine['first_name'] = Str::beforeLast($dev['userAndLocation']['fullName'], ' ');
            $csvLine['last_name'] = Str::afterLast($dev['userAndLocation']['fullName'], ' ');
            $csvLine['phone_number'] = null;
            $csvLine['address'] = null;
            $csvLine['city'] = null;
            $csvLine['postal_code'] = null;
            $csvLine['country'] = null;
            $csvLine['employee_number'] = null;
            $csvLine['cost_centre'] = null;
            $csvLine['job_title'] = null;
            $csvLine['start_date'] = null;
            $csvLine['location_id'] = null;
            $csvLine['budget_id'] = null;
            $csvLine['date_of_birth'] = null;
            $csvLine['send_invitation'] = null;

            $csvLine['device_name'] = $dev['name'] == '' ? '-uten-navn-': $dev['name'];
            $csvLine['serial_number'] = $dev['serialNumber'];
            $csvLine['imei'] = null;
            if (Str::contains($dev['model'], 'iPad')):
                $csvLine['category'] = 'tablets';
            elseif (Str::contains($dev['model'], 'iPhone')):
                $csvLine['category'] = 'phones';
            elseif (Str::contains($dev['model'], 'Apple TV')):
                $csvLine['category'] = 'smart_tvs';
            else:
                $csvLine['category'] = 'other';
            endif;

            $csvLine['payor'] = null;
            $csvLine['distributor_name'] = null;
            $csvLine['distributor_order_id'] = null;

            $csvLine['notes'] = null;
            $csvLine['manager_notes'] = 'Importert fra Jamf Pro';
            $csvLine['purchased_at'] = null;
            $csvLine['expires_at'] = null;

            $results[] = $csvLine;
        endforeach;

        return $results;
    }

    /**
     * Ekporterer array til CSV-format
     */
    protected function toCsv($array): bool {
        $lines = [];
        try {
            $fp = fopen($this->csvFile, 'w');
            fputs($fp, $bom = ( chr(0xEF) . chr(0xBB) . chr(0xBF) ));
            foreach ($array as $line):
                fputcsv($fp, $line, ";");
            endforeach;
            fclose($fp);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}
