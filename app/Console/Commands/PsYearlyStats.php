<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\{Carbon, Str};
use App\Services\{PsApi, Tools};

class PsYearlyStats extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'pureservice:yearly-stats {year}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Henter ut statistikk fra Pureservice basert på oppgitt år';

    protected int $year;
    /**
     * Execute the console command.
     */
    public function handle() {
        $this->year = $this->argument('year') ? $this->argument('year'): Carbon::now()->format('Y');
        $this->newLine();
        $this->info('Statistikk for året '.$this->year);
        $this->newLine();
        $config = config('pureservice.yearlystats');

        $table_fields = ['Beskrivelse', 'Antall'];
        $table_data = [];
        $ps = new PsApi();

        foreach ($config as $row):
            $row['params']['filter'] = Str::replace('*YEAR*', $this->year, $row['params']['filter']);
            $response = $ps->apiQuery($row['uri'], $row['params'], true);
            if ($response->successful()):
                $table_data[] = [$row['title'], $response->json('count')];
            endif;
        endforeach;
        $this->table($table_fields, $table_data);

        return Command::SUCCESS;
    }
}
