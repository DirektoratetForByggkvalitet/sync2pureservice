<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\PsRepeatingTasks;

class PsCheckRepeatingTasks extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ps:repeating-tasks';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Henter opp ressurser for repeterende hendelser og oppretter nye saker eller oppgaver basert på dem';

    protected PsRepeatingTasks $ps;

    /**
     * Execute the console command.
     */
    public function handle() {
        $this->ps = new PsRepeatingTasks();
    }
}
