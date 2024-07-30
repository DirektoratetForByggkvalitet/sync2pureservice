<?php

namespace App\Commands;

use Illuminate\Console\Scheduling\Schedule;
use LaravelZero\Framework\Commands\Command;
use Illuminate\Support\Facades\{Storage};
use App\Services\Tools;


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
    public function handle() {
        $this->info('### NULLSTILLER DATABASEN ###');
        $this->call('migrate:fresh', ['--force' => true]);
        $this->newLine();
        $this->info(Tools::L1.'Rydder opp mapper');
        $this->line(Tools::L2.config('eformidling.path.download'));
        Storage::deleteDirectory(config('eformidling.path.download'));
        $this->line(Tools::L2.config('eformidling.path.temp'));
        Storage::deleteDirectory(config('eformidling.path.temp'));
        $this->line(Tools::L2.config('pureservice.api.dlPath'));
        Storage::deleteDirectory(config('pureservice.api.dlPath'));
    }
}
