<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\{Eformidling, PsApi, Tools};
use App\Models\{Message, Company};
use Illuminate\Support\{Str, Arr};


class IncomingMessages extends Command {
    protected float $start;
    protected string $version = '1.0';
    protected int $count = 0;
    protected Eformidling $ip;
    protected PsApi $ps;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'eformidling:inn {--reset-db : Nullstiller databasen før kjøring}';

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

        if ($this->option('reset-db')):
            $this->info('### NULLSTILLER DATABASEN ###');
            $this->call('migrate:fresh', ['--force' => true]);
            $this->newLine(2);
        endif;

        $this->ip = new Eformidling();
        $this->info(Tools::l1().'Bruker '.$this->ip->getBaseUrl().' som integrasjonspunkt');
        $messages = $this->ip->getIncomingMessages();
        if (!$messages):
            $this->info('Ingen meldinger å behandle. Avslutter etter '.round((microtime(true) - $this->start), 0).' sekunder.');
            return Command::SUCCESS;
        endif;
        $this->count = count($messages);
        $this->info(Tools::l1().'Behandler totalt '.$this->count.' innkommende meldinger');
        foreach ($messages as $m):
            $msgId = $this->ip->getMsgDocumentIdentification($m);
            $this->line(Tools::l1().'Behandler meldingen \''.$msgId['instanceIdentifier'].'\'');
            $msg = $this->ip->peekIncomingMessageById($msgId['instanceIdentifier']);
            if (!$msg):
                if (!$dbMessage = Message::find($msgId['instanceIdentifier'])):
                    $this->error(Tools::l2().'Meldingen er låst og kan ikke lastes ned (enda). Vent i noen minutter.');
                    return Command::FAILURE;
                else:
                    $this->line(Tools::l2().'Meldingen er låst i integrasjonspunktet, men ble funnet i DB. Fortsetter.');
                    $msg = $m;
                    unset($dbMessage);
                endif;
            endif;
            if ($dbMessage = $this->ip->storeIncomingMessage($msg)):
                $this->line(Tools::l2().'Dokumentet ble lagret i DB');
                if ($attCount = $this->ip->downloadMessageAttachments($msgId['instanceIdentifier'])):
                    $this->line(Tools::l2().$attCount .' vedlegg er lastet ned og knyttet til meldingen');
                else:
                    $this->error(Tools::l2().'Fikk ikke lastet ned vedlegg');
                    return Command::FAILURE;
                endif;
            endif;
            $this->newLine();
        endforeach;

        $this->newLine();
        $this->info(Tools::l1().'Importerer meldinger til Pureservice');
        $this->info(Tools::l1().'Bruker Pureservice-instansen '.$this->ps->getBaseUrl().'.');
        $this->ps = new PsApi();
        $this->ps->setTicketOptions('eformidling');
        // $bar = $this->output->createProgressBar(Message::count());
        // $bar->setFormat('verbose');
        // $bar->start();
        $tickets = [];
        $it = 0;
        $msgCount = count(Message::all(['id']));
        foreach(Message::lazy() as $message):
            $it++;
            $sender = Company::find($message->sender_id);
            $this->line(Tools::l1().$it.'/'.$msgCount.': '. $message->id.' - '. $message->documentType().' fra '.$sender->name);
            if ($message->documentType() == 'innsynskrav'):
                // Innsynskrav
                $this->ps->setTicketOptions('innsynskrav');
                $this->line(Tools::l2().'Splitter innsynskravet opp basert på arkivsaker');
                if ($new = $message->splittInnsynskrav($this->ps)):
                    $this->line(Tools::l2().count($new).' innsynskrav ble opprettet i Pureservice:');
                    foreach ($new as $i):
                        $this->line(Tools::l3().'- Sak ID '.$i->requestNumber. ' "'.$i->subject.'"');
                    endforeach;
                    array_merge($tickets, $new);
                    unset($new);
                else:
                    $this->error(Tools::l2().'Klarte ikke å splitte innsynskravet');
                    $this->newLine();
                    continue;
                endif;
             else:
                // Alle andre typer meldinger
                $this->line(Tools::l2().'Oppretter sak i Pureservice');
                $this->ps->setTicketOptions('eformidling');
                if ($new = $message->toPsTicket($this->ps)):
                    $tickets[] = $new;
                    $this->line(Tools::l3().'Sak ID '.$new->requestNumber. ' ble opprettet.');
                    // unset($new);
                else:
                    $this->error(Tools::l2().'Klarte ikke å opprette sak i Pureservice');
                    $this->newLine();
                    continue;
                endif;
            endif;
            // $bar->advance();
            // Vi har tatt vare på meldingen. Sletter den fra eFormidling sin kø
            if ($this->ip->deleteIncomingMessage($message->messageId)):
                $this->line(Tools::l3().'Meldingen har blitt slettet fra integrasjonspunktet');
            else:
                $this->line(Tools::l3().'Meldingen ble IKKE slettet fra integrasjonspunktet, og vil bli behandlet igjen senere.');
            endif;

            $this->newLine();
        endforeach;
        // $bar->finish();
        $this->info('Ferdig. Operasjonen tok '.round((microtime(true) - $this->start), 0).' sekunder');
        $this->info(count($tickets).' sak'.count($tickets) > 1 ? 'er' : ''.' ble opprettet');
        return Command::SUCCESS;
    }
}
