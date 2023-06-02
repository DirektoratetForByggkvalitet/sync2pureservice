<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\{NextMove, Pureservice, Tools};


class IncomingMessages extends Command
{
    protected float $start;
    protected string $version = '0.1';
    protected NextMove $ip;
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

        $this->ip = new NextMove();
        //dd($this->ip->getOptions());
        $messages = $this->ip->getIncomingMessages();
        dd($messages);

        $this->info('Ferdig. Operasjonen tok '.round((microtime(true) - $this->start), 0).' sekunder');
        return Command::SUCCESS;
    }
}
