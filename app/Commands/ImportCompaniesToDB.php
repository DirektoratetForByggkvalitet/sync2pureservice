<?php

namespace App\Commands;

use Illuminate\Console\Scheduling\Schedule;
use LaravelZero\Framework\Commands\Command;
use App\Services\{PsApi, Tools};
use App\Models\Company;

class ImportCompaniesToDB extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'pureservice:companies2db {category}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Henter inn firma fra Pureservice og lagrer dem i lokal database';

    protected string $version = '1.0';
    protected PsApi $ps;
    protected float $start;
    protected string $category;
    /**
     * Execute the console command.
     */
    public function handle() {
        $this->start = microtime(true);
        $this->info(class_basename($this).' v'.$this->version);
        $this->line($this->description);
        $this->newLine(2);
        $this->category = $this->argument('category');
        $this->ps = new PsApi();

        $uri = '/company/';
        $limit = 300;
        $params = [
            'include' => 'emailaddress',
            'limit' => $limit,
            'start' => 0,
            'sort' => 'name ASC',
            'filter' => '!disabled AND '.config('pureservice.company.categoryfield').' == "'.$this->category.'"',
        ];
        $batchCount = $limit;
        $rTotal = 0;
        while ($batchCount == $limit):
            $batchCount = 0;
            $response = $this->ps->apiQuery($uri, $params, true);
            if ($response->successful()):
                $companies = collect($response->json('companies'))->mapInto(Company::class);
                $emails = collect($response->json('linked.companyemailaddresses'));
                $batchCount = $companies->count();
                $companies->each(function (Company $company) use ($emails) {
                    if ($emailAddress = $emails->firstWhere('companyId', $company->id)):
                        $company->email = $emailAddress['email'];
                    endif;
                    $company->save();
                });
                $params['start'] += $limit;
                $rTotal += $batchCount;
                $this->info(Tools::L2.$rTotal.' hentet');
            endif;
        endwhile;
        $this->newLine();
        $this->info('Databasen inneholder nå '.Company::all(['id'])->count().' firma');
    }
}
