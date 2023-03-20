<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\{ExcelLookup, Pureservice, Tools};

class Kommune2Pureservice extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'pureservice:kommuner2ps';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Importerer kommuner inn i Pureservice, ved å opprette dem som firma og postmottak-bruker';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        //
    }
}
