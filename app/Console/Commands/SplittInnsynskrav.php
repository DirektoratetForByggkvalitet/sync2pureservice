<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\{Pureservice};

class SplittInnsynskrav extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'innsynskrav:splitt {sakId}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Splitter et innsynskrav i Pureservice gitt med SakID';

    protected Pureservice $ps;
    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): void
    {
        return Command::SUCCESS;
    }
}
