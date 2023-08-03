<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\{Eformidling, PsApi, Tools};
use App\Models\Message;
use Illuminate\Support\{Str, Arr};


class IncomingMessages extends Command
{
    protected float $start;
    protected string $version = '0.2';
    protected int $count = 0;
    protected Eformidling $ip;
    protected PsApi $ps;
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

        $this->ip = new Eformidling();
        $messages = $this->ip->getIncomingMessages();
        if (!$messages):
            $this->info('Ingen meldinger å behandle. Avslutter etter '.round((microtime(true) - $this->start), 0).' sekunder.');
            return Command::SUCCESS;
        endif;
        $this->count = count($messages);
        $this->info(Tools::l1().'Behandler totalt '.$this->count.' innkommende meldinger');
        foreach ($messages as $m):
            $msgId = $this->ip->getMsgDocumentIdentification($m);
            $msg = $this->ip->peekIncomingMessageById($msgId['instanceIdentifier']);
            $this->line(Tools::l1().'Behandler dokumentet \''.$msgId['instanceIdentifier'].'\'');
            if ($this->ip->storeMessage($msg)):
                $this->line(Tools::l2().'Dokumentet ble lagret i DB');
            endif;
            if ($this->ip->storeAttachments($msg)):
                $this->line(Tools::l2().'Vedlegg ble lastet ned og knyttet til meldingen');
            endif;
            $this->newLine();
        endforeach;

        $this->newLine();
        $this->info(Tools::l1().'Importerer meldinger til Pureservice');
        $this->ps = new PsApi();
        $this->ps->setCKey(Str::lower(class_basename($this->ip)));
        $this->ps->setTicketOptions();
        $bar = $this->output->createProgressBar(Message::count());
        $bar->setFormat('verbose');
        $bar->start();
        foreach(Message::lazy() as $message):
            $message->toPsTicket($this->ps);
            $bar->advance();
        endforeach;
        $bar->finish();
        $this->info('Ferdig. Operasjonen tok '.round((microtime(true) - $this->start), 0).' sekunder');
        return Command::SUCCESS;
    }
}
