<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\{Eformidling, PsApi, Tools};
use App\Models\{Message, Company};
use Illuminate\Support\{Str, Arr};
use Illuminate\Support\Facades\{Storage};

class IncomingMessages extends Command {
    protected float $start;
    protected string $version = '1.0';
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
            $this->newLine();
            $this->info('Ingen meldinger å behandle. Avslutter etter '.round((microtime(true) - $this->start), 0).' sekunder.');
            return Command::SUCCESS;
        endif;
        $skipCount = $messages->lazy()->whereIn('standardBusinessDocumentHeader.documentIdentification.type', ['einnsyn_kvittering'])->count();

        $this->info(Tools::l1().'Totalt '.$messages->count().' innkommende meldinger. '.$skipCount.' av disse er kvitteringer fra eInnsyn, som vi hopper over.');

        // Avslutter dersom alle meldinger skal hoppes over.
        if ($messages->count() == $skipCount):
            $this->newLine();
            $this->info('Ingen meldinger å behandle. Avslutter etter '.round((microtime(true) - $this->start), 0).' sekunder.');
            return Command::SUCCESS;
        endif;
        $i = 0;
        $subtotal = $messages->count() - $skipCount;
        foreach ($messages->lazy()->whereNotIn('standardBusinessDocumentHeader.documentIdentification.type', ['einnsyn_kvittering']) as $m):
            $i++;
            $msgId = $this->ip->getMsgDocumentIdentification($m);
            $this->line(Tools::l1().$i.'/'.$subtotal.' Behandler meldingen \''.$msgId['instanceIdentifier'].'\'');

            // Hopper over kvitteringsmeldinger fra eInnsyn, for nå.
            if ($this->ip->getMessageDocumentType($m) == 'einnsyn_kvittering'):
                $this->line(Tools::l2().'eInnsynskvittering, hopper over');
                $this->newLine();
                continue;
            endif;
            $lock = $this->ip->peekIncomingMessageById($msgId['instanceIdentifier']);
            if ($lock->successful()):
                $this->line(Tools::l2().'Meldingen \''. $lock->json('standardBusinessDocumentHeader.documentIdentification.instanceIdentifier', '[Finner ikke meldings-ID]') .'\' har blitt låst og er klar for nedlasting.');
                //dd($lock->body());
            else:
                //dd($lock->body());
                $this->line(Tools::l2().'Meldingen er allerede låst. Fortsetter med nedlasting.');
            endif;
            if ($dbMessage = Message::firstWhere('messageId', $msgId['instanceIdentifier'])):
                $this->line(Tools::l2().'Meldingen er allerede lagret i databasen');
            elseif ($dbMessage = $this->ip->storeIncomingMessage($m)):
                $this->line(Tools::l2().'Meldingen ble lagret i DB');
            endif;
            $dbMessage->assureAttachments();
            $dbMessage->syncChanges();
            if ($dbMessage->attachments == []):
                $downloadedFiles = $this->ip->downloadIncomingAsic($msgId['instanceIdentifier'], $dbMessage->downloadPath());
                $this->line(Tools::l2().count($downloadedFiles).' vedlegg ble lastet ned og knyttet til meldingen');
            else:
                $tmp = is_array($dbMessage->attachments) ? $dbMessage->attachments : [];
                $this->line(Tools::l2().count($tmp).' vedlegg er allerede lastet ned. Fortsetter...');
            endif;
            // Sjekker om meldingen mangler korrekt avsender
            $sender = $dbMessage->sender();
            if ($sender->name == 'Virksomhet ikke i BRREG'):
                $this->line(Tools::L2.'Prøver å korrigere feil med forsendelsens avsender: '.$sender->name.' - '.$sender->organizationNumber);
                $dlPath = $dbMessage->downloadPath();
                $xmlfil = $dlPath.'/arkivmelding.xml';
                if (Storage::exists($xmlfil)):
                    // arkivmelding.xml vil inneholde avsenders navn
                    $arkivmelding = json_decode(json_encode(simplexml_load_file(Storage::path($xmlfil))), true);
                    if ($kparter = Arr::get($arkivmelding, 'mappe.basisregistrering.korrespondansepart', null)):
                        // Avklarer avsender, også hvis det bare er én korrespondansepart
                        $avsender = isset($kparter['korrespondanseparttype']) ? $kparter : collect($kparter)->firstWhere('korrespondanseparttype', 'Avsender');
                        $this->ensurePs();
                        if ($psSender = $this->ps->findCompany(null, $avsender['korrespondansepartNavn'])):
                            $this->line(Tools::L3.'Fant avsender: '.$psSender['name']);
                            foreach (['id', 'name', 'organizationNumber', 'website'] as $f):
                                $sender->$f = $psSender[$f];
                            endforeach;
                            $sender->save();
                        endif;
                    endif;
                endif;
            endif;
            $this->newLine();
        endforeach;
        unset($messages, $messagesToSkip, $dbMessage);

        $this->newLine();
        $this->info(Tools::l1().'Oppretter meldinger som saker i Pureservice');
        $this->ensurePs();
        $this->info(Tools::l1().'Bruker Pureservice-instansen '.$this->ps->getBaseUrl().'.');
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
            $this->line(Tools::l1().$it.'/'.$msgCount.': '. $message->messageId.' - '. $message->documentType().' fra '.$sender->name.' - '.$sender->organizationNumber);
            if ($sender->name == 'Virksomhet ikke i BRREG'):
                $this->error(Tools::L2.'Hopper over denne meldingen inntil løsning for mottak er ferdigstilt.');
                continue;
            endif;
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
                if ($new = $message->saveToPs($this->ps)):
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
                $this->error(Tools::l3().'Meldingen ble IKKE slettet fra integrasjonspunktet.');
                $this->line(Tools::l3().'Enten er den allerede slettet, eller så oppsto det en feil under sletting.');
                $this->line(Tools::l3().'Hvis det oppsto en feil KAN meldingen bli behandlet igjen neste gang vi sjekker.');
            endif;

            $this->newLine();
        endforeach;
        // $bar->finish();
        $this->info('Ferdig. Operasjonen tok '.round((microtime(true) - $this->start), 0).' sekunder');
        //$this->info(count($tickets).' sak'.count($tickets) > 1 ? 'er' : ''.' ble opprettet');
        return Command::SUCCESS;
    }

    /**
     * Sikrer at det er opprettet en instans av tjenesten PsApi
     */
    protected function ensurePs() : void {
        if (!isset($this->ps)) $this->ps = new PsApi();
    }
}
