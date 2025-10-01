<?php

namespace App\Commands;

use Illuminate\Console\Scheduling\Schedule;
use LaravelZero\Framework\Commands\Command;
use App\Services\{Eformidling, PsApi, Tools};
use App\Models\{Message, Company};
use Illuminate\Support\{Str, Arr};
use Illuminate\Support\Facades\{Storage};

class IncomingMessages extends Command {
    protected float $start;
    protected string $version = '1.5.1';
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
        $this->info(Tools::L1.'Bruker '.$this->ip->getBaseUrl().' som integrasjonspunkt');
        $this->line('Henter innkommende meldinger');
        $messages = $this->ip->getIncomingMessages();
        if (!$messages):
            $this->noMessages();
            return Command::SUCCESS;
        endif;
        $skipCount = $messages->lazy()->whereIn('standardBusinessDocumentHeader.documentIdentification.type', ['einnsyn_kvittering'])->count();

        $this->info(Tools::L1.'Totalt '.$messages->count().' innkommende meldinger. '.$skipCount.' av disse er kvitteringer, som vi hopper over.');

        // Avslutter dersom alle meldinger skal hoppes over.
        if ($messages->count() == $skipCount):
            $this->noMessages();
            return Command::SUCCESS;
        endif;
        $i = 0;
        $subtotal = $messages->count() - $skipCount;
        foreach ($messages->lazy()
            ->whereNotIn('standardBusinessDocumentHeader.documentIdentification.type', ['einnsyn_kvittering'])
            ->sortByDesc('standardBusinessDocumentHeader.documentIdentification.type') as $m
        ):
            $i++;
            $msgId = $this->ip->getMsgDocumentIdentification($m);
            $this->line(Tools::L1.$i.'/'.$subtotal.' Behandler meldingen \''.$msgId['instanceIdentifier'].'\'');

            // Hopper over kvitteringsmeldinger fra eInnsyn, inntil videre.
            if ($this->ip->getMessageDocumentType($m) == 'arkivmelding_kvittering'):
                $this->line(Tools::L2.'Kvittering for arkivmelding, sletter den fra integrasjonspunktet');
                $this->ip->peekIncomingMessageById($msgId['instanceIdentifier']);
                $this->ip->deleteIncomingMessage($msgId['instanceIdentifier']);
                $this->newLine();
                continue;
            endif;
            $lock = $this->ip->peekIncomingMessageById($msgId['instanceIdentifier']);
            if ($lock->successful()):
                $this->line(Tools::L2.'Meldingen \''. $lock->json('standardBusinessDocumentHeader.documentIdentification.instanceIdentifier', '[Finner ikke melding-ID]') .'\' har blitt låst og er klar for nedlasting.');
                //dd($lock->body());
            else:
                //dd($lock->body());
                $this->line(Tools::L2.'Meldingen er allerede låst. Fortsetter med nedlasting.');
            endif;
            if ($dbMessage = Message::firstWhere('messageId', $msgId['instanceIdentifier'])):
                $this->line(Tools::L2.'Meldingen er allerede lagret i databasen');
            elseif ($dbMessage = $this->ip->storeIncomingMessage($m)):
                $this->line(Tools::L2.'Meldingen ble lagret i DB');
            endif;
            $dbMessage->assureAttachments();
            $dbMessage->syncChanges();
            if ($dbMessage->attachments == []):
                $downloadedFiles = $this->ip->downloadIncomingAsic($msgId['instanceIdentifier'], $dbMessage->downloadPath());
                $this->line(Tools::L2.count($downloadedFiles).' vedlegg ble lastet ned og knyttet til meldingen');
            else:
                $tmp = is_array($dbMessage->attachments) ? $dbMessage->attachments : [];
                $this->line(Tools::L2.count($tmp).' vedlegg er allerede lastet ned. Fortsetter...');
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

        $this->newLine(2);
        $this->info(Tools::L1.'Oppretter meldinger som saker i Pureservice');
        $this->ensurePs();
        $this->info(Tools::L1.'Bruker Pureservice-instansen '.$this->ps->getBaseUrl().'.');
        $this->ps->setTicketOptions('eformidling');
        // $bar = $this->output->createProgressBar(Message::count());
        // $bar->setFormat('verbose');
        // $bar->start();
        $tickets = [];
        $it = 0;
        $msgCount = count(Message::all(['id']));
        foreach(Message::lazy() as $message):
            $it++;
            $deleteMessage = true;
            $sender = Company::find($message->sender_id);
            $this->line(Tools::L1.$it.'/'.$msgCount.': '. $message->messageId.' - '. $message->documentType().' fra '.$sender->name.' - '.$sender->organizationNumber);
            if ($sender->name == 'Virksomhet ikke i BRREG'):
                $this->error(Tools::L2.'Hopper over denne meldingen inntil løsning for mottak er ferdigstilt.');
                continue;
            endif;
            if ($message->documentType() == 'innsynskrav'):
                /**
                 * INNSYNSKRAV
                 */
                $this->ps->setTicketOptions('innsynskrav');
                $this->line(Tools::L2.'Splitter innsynskravet opp basert på arkivsaker');
                $newTickets = $message->splittInnsynskrav($this->ps);
                if ($newTickets !== false):
                    $this->line(Tools::L2.count($newTickets).' innsynskrav ble opprettet i Pureservice:');
                    foreach ($newTickets as $i):
                        $this->line(Tools::L3.'- Sak ID '.$i->requestNumber. ' "'.$i->subject.'"');
                    endforeach;
                    unset($newTickets);
                else:
                    $deleteMessage = false;
                    $this->error(Tools::L2.'Klarte ikke å splitte innsynskravet');
                    $this->newLine();
                    continue;
                endif;
             else:
                // Alle andre typer meldinger
                $this->line(Tools::L2.'Oppretter sak i Pureservice');
                $this->ps->setTicketOptions('eformidling');
                if ($new = $message->saveToPs($this->ps)):
                    $tickets[] = $new;
                    $this->line(Tools::L3.'- Sak ID '.$new->requestNumber. ' ble opprettet.');
                    // unset($new);
                else:
                    $deleteMessage = false;
                    $this->error(Tools::L2.'Klarte ikke å opprette sak i Pureservice');
                    $this->newLine();
                    continue;
                endif;
            endif;
            // $bar->advance();
            // Vi har tatt vare på meldingen. Sletter den fra eFormidling sin kø
            if ($deleteMessage && $this->ip->deleteIncomingMessage($message->messageId)):
                $this->line(Tools::L3.'Meldingen har blitt slettet fra integrasjonspunktet');
            else:
                $this->error(Tools::L3.'Meldingen ble IKKE slettet fra integrasjonspunktet.');
                if ($this->ps->error_json):
                    $this->error(Tools::L3.'Feilmelding:');
                    $this->info($this->ps->error_json);
                endif;
                $this->line(Tools::L3.'Hvis det oppsto en feil vil meldingen bli behandlet igjen neste gang vi sjekker.');
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

    protected function noMessages(): void {
        $this->newLine();
        $this->info('Integrasjonspunktet fant ingen uleste meldinger. Dersom SvarUt sier at det er meldinger i køen som ikke er hentet må du laste den ned manuelt fra https://svarut.ks.no, for så å sette meldingen til mottatt.');
        $this->info('Avslutter etter '.round((microtime(true) - $this->start), 0).' sekunder.');
    }
}
