<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\{Str, Arr};
use App\Services\Tools;
use App\Models\Company;

class Company2Csv extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'company:export2csv';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Eksporterer databasens foretak til en CSV-filen \'companies.csv\' i storage/';

    protected $csvFile;
    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->csvFile = storage_path('companies.csv');
        $fp = fopen($this->csvFile, 'w');
        fputs($fp, $bom = ( chr(0xEF) . chr(0xBB) . chr(0xBF) ));
        fputcsv($fp, ['regnr', 'knr', 'navn', 'e-post', 'nettside', 'kategori', 'notater'], ';');
        foreach (Company::lazy() as $company):
            $line = [
                $company->organizationNumber,
                $company->companyNumber,
                $company->name,
                $company->email,
                $company->website,
                $company->category,
                $company->notes,
            ];
            fputcsv($fp, $line, ';');
        endforeach;
        fclose($fp);
    }

}
