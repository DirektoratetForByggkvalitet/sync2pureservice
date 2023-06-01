<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\{NextMove, Pureservice, Tools};

class IncomingMessages extends Command
{
    protected $start;
    protected $version = '0.1';
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'forsendelse:inn';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Bruker integrasjonspunktet til å hente innkommende meldinger';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int {
        $this->start = microtime(true);
        $this->info(class_basename($this).' v'.$this->version);
        $this->line($this->description);
        $this->newLine(2);


        $this->info('Ferdig. Operasjonen tok '.round(microtime(true)-$this->start, 0).' sekunder');
        return Command::SUCCESS;
    }
}
