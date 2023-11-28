<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\{Arr, Str, Collection};
use Illuminate\Support\Facades\{Storage, Mail};
use App\Services\{Eformidling, PsApi, Tools};
use App\Models\{Message, Company, User};
use App\Mail\TicketMessage;
use Barryvdh\DomPDF\Facade\Pdf as PDF;

class Utsending extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'pureservice:utsending';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Henter ut masseutsendelser som skal sendes med e-post og eFormidling, samt utgående meldinger som skal sendes med eFormidling';
    protected string $version = '1.0';
    protected float $start;
    protected Collection $messages;
    protected PsApi $api;
    protected Eformidling $ef;
    protected Company $sender;
    protected array $results = [
        'eFormidling' => 0,
        'email' => 0,
        'skipped' => 0,
        'saker' => 0,
    ];
    protected array $recipientListAssetType;
    /**
     * Execute the console command.
     */
    public function handle(): int {
        $this->start = microtime(true);
        $this->info(class_basename($this).' v'.$this->version);
        $this->line($this->description);
        $this->newLine(2);

        $this->info(Tools::ts().'Kobler til Pureservice');
        $this->comment(Tools::l3().'Bruker '.config('pureservice.api.url'));
        $this->api = new PsApi();

        // Henter inn AssetType for mottakerlistene
        $this->recipientListAssetType = $this->api->getEntityByName('assettype', config('pureservice.dispatch.assetTypeName'));

        $uri = '/email/';
        $failedStatus = config('pureservice.email.status.failed');
        $params = [
            'filter' => 'status == '.$failedStatus,
            'sort' => 'created DESC',
            'include' => 'attachments',
        ];

        $response = $this->api->apiQuery($uri, $params, true);

        if ($response->failed()):
            $this->error('Feil ved innhenting av meldinger');
            return Command::FAILURE;
        endif;

        $messages = collect($response->json('emails'));
        if ($messages->count() == 0):
            $this->info(Tools::L1.'Ingen meldinger ble funnet.');
            return Command::SUCCESS;
        endif;
        $this->messages = $messages->filter(function (array $item, int $key) {
            if ($item['to'] == config('pureservice.dispatch.address_email') ||
                $item['to'] == config('pureservice.dispatch.address_ef') ||
                Str::endsWith($item['to'], config('pureservice.user.ef_domain'))):
                return true;
            endif;
        });
        unset($messages);
        $msgCount = $this->messages->count();
        if ($msgCount == 0):
            $this->info(Tools::L1.'Fant ingen meldinger som kan sendes med eFormidling');
            return Command::SUCCESS;
        endif;

        $this->info(Tools::L1.'Fant '.$msgCount.' melding(er) som skal sendes med eFormidling');
        $this->ef = new Eformidling();
        $this->sender = $this->api->getSelfCompany();
        $this->sender->save();
        $this->messages->each(function (array $email, int $key) {
            $ticket = $this->api->getTicketFromPureservice($email['requestId'], false);
            $this->results['saker']++;
            $this->line(Tools::L1.'Behandler melding med emne \''.$email['subject'].'\'');
            // Laster ned vedlegg til meldingen
            $dlPath = $ticket->getDownloadPath();
            // Hoveddokument, brukes av eFormidling
            $mainDocument = $dlPath.'/melding.pdf';
            $msgAttachments = [];
            $attachmentIds = $email['links']['attachments']['ids'];
            if (count($attachmentIds)):
                $msgAttachments = $this->api->downloadAttachmentsById($attachmentIds, $dlPath);
            endif;

            if ($email['to'] == config('pureservice.dispatch.address_email') ||
                $email['to'] == config('pureservice.dispatch.address_ef')):
                // Dette er en masseutsendelse
                // Henter inn mottakerne fra mottakerlister
                $recipients = $ticket->extractRecipientsFromAsset($this->api, $this->recipientListAssetType);
            else:
                $toRegNo = Str::before($email['to'], '@');
                $receiver = $this->api->findCompany($toRegNo, null, true);
                $receiver->save();
                $recipients = collect([$receiver]);
                unset($receiver);
            endif;
            // Skal vi foretrekke eFormidling?
            $preferEformidling = (Str::endsWith($email['to'], config('pureservice.user.ef_domain')));
            foreach ($recipients as $receiver):
                // Forsendelseskanal kan endre seg for hver mottaker
                $sendViaEformidling = $preferEformidling;
                if ($receiver instanceof User):
                    $isUser = true;
                    $sendViaEformidling = false;
                    $this->line(Tools::L2.'Adressert til \''.$receiver->firstName.' '. $receiver->lastName.'\' - '.$receiver->email);
                else:
                    $isUser = false;
                    if (!$receiver->organizationNumber):
                        $sendViaEformidling = false;
                    endif;
                    if ($sendViaEformidling):
                        $this->line(Tools::L2.'Adressert til \''.$receiver->name.'\' - '.$receiver->organizationNumber);
                    else:
                        $this->line(Tools::L2.'Adressert til \''.$receiver->name.'\' - '.$receiver->email);
                    endif;
                endif;
                if ($sendViaEformidling):
                    $message = Message::factory()->create([
                        'sender_id' => $this->sender->id,
                        'receiver_id' => $receiver->id,
                    ]);
                    // Oppretter hoveddokumentet (meldingen)
                    PDF::loadHTML($email['html'])->save(Storage::path($mainDocument));
                    $msgAttachments[] = $mainDocument;

                    $message->attachments = $msgAttachments;
                    $message->createContent();
                    $message->setMainDocument($mainDocument);
                    $message->createXmlFromTicket($ticket);
                    // Oppretter og sender meldingen på integrasjonspunktet
                    $this->line(Tools::L3.'Oppretter forsendelse i eFormidling');
                    $created = $this->ef->createArkivmelding($message);
                    $this->line(Tools::L3.'Laster opp vedlegg');
                    $this->ef->uploadAttachments($message);
                    //$sent = $this->ef->sendMessage($message);
                    $this->line(Tools::L3.'Sender meldingen');
                    $this->ef->dispatchMessage($message);
                    $this->results['eFormidling']++;
                else:
                    // Sendes som e-post
                    if (!$receiver->email):
                        $this->results['skipped']++;
                        $this->error(Tools::L3.'Kan ikke sendes. E-postadresse mangler');
                        continue;
                    endif;
                    Mail::to($receiver->email)->send(new TicketMessage($ticket, false, $email['subject'], $email['html'], $msgAttachments));
                    $this->line(Tools::L3.'Sendt med e-post');
                    $this->results['email']++;
                endif;
            endforeach; // $recipients
            // Merker meldingen som sendt i Pureservice

            if ($sent = $this->api->setEmailStatus($email['id'], config('pureservice.email.status.sent'))):
                $this->line(Tools::L2.'Meldingen ble merket sendt i Pureservice');
            else:
                $this->error(Tools::L2.'Meldingen kunne ikke merkes som sendt i Pureservice');
            endif;
            $this->newLine(2);
        }); // $this->messages->each()

        $this->info('### Ferdig ###');
        $this->info('Operasjonen ble fullført på '.round(microtime(true) - $this->start, 0).' sekunder');
        $this->line(Tools::L2.'Antall eFormidling-forsendelser: '.$this->results['eFormidling']);
        $this->line(Tools::L2.'Antall e-post sendt: '.$this->results['email']);
        $this->line(Tools::L2.'Antall manglende adresser: '.$this->results['skipped']);
        $this->line(Tools::L2.'Antall saker: '.$this->results['saker']);
        return Command::SUCCESS;
    }
}
