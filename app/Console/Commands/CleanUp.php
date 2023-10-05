<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\{Storage};

class CleanUp extends Command {
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:clean-up';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Rydder databasen og sletter nedlastingsmapper';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('### NULLSTILLER DATABASEN ###');
        $this->call('migrate:fresh', ['--force' => true]);
        $this->newLine();
        $this->info('Rydder opp mapper');
        Storage::deleteDirectory(config('eformidling.path.download'));
        Storage::deleteDirectory(config('eformidling.path.temp'));
    }
}
