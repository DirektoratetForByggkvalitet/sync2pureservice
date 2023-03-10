<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\{Tools, Pureservice};
use Carbon\Carbon;
use Illuminate\Support\{Arr, Str};

class PSUtsendelse extends Command {
    protected $start;
    protected Pureservice $ps;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'pureservice:utsendelse';

    protected $version = '0.1';
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Ser etter saker som skal sendes ut som masseutsendelser, og utfører dem';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $this->start = microtime(true);
        $this->info(class_basename($this).' v'.$this->version);
        $this->line($this->description);

        $this->info($this->ts().'Setter opp miljøet...');

        $this->info($this->ts().'Kobler til Pureservice');
        $this->ps = new Pureservice();

    }
}
