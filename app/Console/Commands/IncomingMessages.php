<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\{NextMove, Pureservice, Tools};


class IncomingMessages extends Command
{
    protected float $start;
    protected string $version = '0.2';
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
        $messages = $this->ip->getIncomingMessages();
        $msgCount = count($messages);
        if (!$msgCount):
            $this->info('Ingen meldinger å behandle. Avslutter etter '.round((microtime(true) - $this->start), 0).' sekunder.');
            return Command::SUCCESS;
        endif;

        foreach ($messages as $msg):
            $msgId = $this->ip->getMsgId($msg);
            $this->line(Tools::l2().'Behandler dokumentet \''.$msgId.'\'');
            if ($this->ip->storeMsg($msg)):
                $this->line(Tools::l3().'Dokumentet ble lasted ned og lagret i DB');
            endif;
        endforeach;

        $this->info('Ferdig. Operasjonen tok '.round((microtime(true) - $this->start), 0).' sekunder');
        return Command::SUCCESS;
    }
}
