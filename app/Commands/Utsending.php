<?php

namespace App\Commands;

use Illuminate\Console\Scheduling\Schedule;
use LaravelZero\Framework\Commands\Command;
use Illuminate\Support\{Str, Collection};
use Illuminate\Support\Facades\{Storage, Mail, Blade};
use App\Services\{Eformidling, PsApi, Tools};
use App\Models\{Message, Company, User, Ticket};
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
    protected string $version = '2.0';
    protected float $start;
    protected Collection $messages;
    protected Collection $tickets;
    protected PsApi $api;
    protected Eformidling $ef;
    protected Company $sender;
    protected array $results = [
        'recipients' => 0,
        'eFormidling' => 0,
        'email' => 0,
        'skipped' => 0,
        'saker' => 0,
        'sakerOpprettet' => [],
    ];
    protected array $recipientListAssetType;
    protected array $ticketsCreated = [];

    /*
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
        $this->api->fetchStatuses();

        // Henter inn AssetType for mottakerlistene
        $this->recipientListAssetType = $this->api->getEntityByName('assettype', config('pureservice.dispatch.assetTypeName'));


        $uri = '/email/';
        $failedStatus = config('pureservice.email.status.failed');
        $params = [
            //'filter' => 'status == '.$failedStatus,
            'sort' => 'created DESC',
            'include' => 'attachments,request',
        ];

        $waitingStatusId = $this->api->findStatus(config('pureservice.ticket.status_message_sent'));
        $params['filter'] = 'direction == '.config('pureservice.comms.direction.out').' AND '.
            'request.statusId == '.$waitingStatusId. ' AND '.
            '('.
                'to == '.Str::wrap(config('pureservice.dispatch.address.ef'), '"').
                ' OR '.
                'to == '.Str::wrap(config('pureservice.dispatch.address.email'),'"').
                ' OR '.
                'to == '.Str::wrap(config('pureservice.dispatch.address.email_121'),'"').
                ' OR '.
                'to.contains('.Str::wrap(config('pureservice.dispatch.ef_domain'), '"').')'.
            ')';

        $response = $this->api->apiQuery($uri, $params, true);

        if ($response->failed()):
            $this->error('Feil ved innhenting av meldinger');
            return Command::FAILURE;
        endif;
        $this->messages = collect($response->json('emails'));
        $this->tickets = collect($response->json('linked.tickets'))->mapInto(Ticket::class);
        $msgCount = $this->messages->count();
        if ($msgCount == 0):
            $this->info(Tools::L1.'Ingen meldinger ble funnet.');
            return Command::SUCCESS;
        endif;
        // $this->messages = $messages->filter(function (array $item, int $key) {
        //     if ($item['to'] == config('pureservice.dispatch.address_email') ||
        //         $item['to'] == config('pureservice.dispatch.address_ef') ||
        //         Str::endsWith($item['to'], config('pureservice.user.ef_domain'))):
        //         return true;
        //     endif;
        // });
        // unset($messages);

        if ($msgCount == 0):
            $this->info(Tools::L1.'Fant ingen meldinger som kan sendes');
            return Command::SUCCESS;
        endif;

        $this->info(Tools::L1.'Fant '.$msgCount.' melding(er) som skal sendes');
        //dd($this->messages);
        $this->ef = new Eformidling();
        $this->sender = $this->api->getSelfCompany();
        $this->sender->save();
        //dd($this->messages);
        $this->messages->each(function (array $email, int $key) {
            $ticket = $this->tickets->firstWhere('id', $email['requestId']); // $this->api->getTicketFromPureservice($email['requestId'], false);
            if ($existing = Ticket::firstWhere('id', $email['requestId'])):
                // Denne saken har vi håndtert tidligere
                $duplicate = true;
            else:
                $duplicate = false;
            endif;
            if ($duplicate):
                $this->error(Tools::L1.'Meldingen med emne \''.$email['subject'].'\' er et duplikat. Vi har allerede sendt en nyere melding.');
            else:
                $ticket->save();
                $autocloseTicket = false;
                $this->results['saker']++;
                $this->line(Tools::L1.'Behandler melding med emne \''.$email['subject'].'\'');
                // Laster ned vedlegg til meldingen
                $dlPath = $ticket->getDownloadPath();
                // Hoveddokument, brukes av eFormidling
                $mainDocument = $dlPath.'/melding.pdf';
                // Oppretter hoveddokumentet (meldingen)
                PDF::loadHTML($email['html'])->save(Storage::path($mainDocument));

                $msgAttachments = [];
                $attachmentIds = $email['links']['attachments']['ids'];
                if (count($attachmentIds)):
                    $msgAttachments = $this->api->downloadAttachmentsById($attachmentIds, $dlPath);
                endif;

                if (in_array($email['to'], config('pureservice.dispatch.address'))):
                    // Dette er en masseutsendelse
                    // Henter inn mottakerne fra mottakerlister
                    $this->line(Tools::L2.'Masseutsending: Henter inn mottakere');
                    $recipients = $ticket->extractRecipientsFromAsset($this->api, $this->recipientListAssetType);
                    $autocloseTicket = true;
                    // Sjekker om vi skal opprette nye saker per mottaker
                    $createNewTickets = $email['to'] == config('pureservice.dispatch.address.email_121');
                else:
                    $toRegNo = Str::before($email['to'], '@');
                    if ($recipient = $this->api->findCompany($toRegNo, null, true)):
                        if ($dbcompany = Company::firstWhere('id', $recipient->id)):
                            $recipient = $dbcompany;
                        endif;
                        $recipient->save();
                        $recipients = collect([$recipient]);
                        unset($recipient);
                    endif;
                endif;
                // Skal vi foretrekke eFormidling?
                $preferEformidling = (Str::endsWith($email['to'], config('pureservice.dispatch.ef_domain')));
                $this->results['recipients'] = $recipients->count();
                foreach ($recipients as $recipient):
                    // Forsendelseskanal kan endre seg for hver mottaker
                    $sendViaEformidling = $preferEformidling;
                    $isUser = $recipient instanceof User;
                    if ($isUser):
                        $sendViaEformidling = false;
                        $this->line(Tools::L2.'Adressert til \''.$recipient->firstName.' '. $recipient->lastName.'\' - '.$recipient->email);
                    else:
                        if ($preferEformidling):
                            $sendViaEformidling = $recipient->organizationNumber ? true : false;
                        endif;
                        if ($sendViaEformidling):
                            $this->line(Tools::L2.'Adressert til \''.$recipient->name.'\' - '.$recipient->organizationNumber);
                        else:
                            $this->line(Tools::L2.'Adressert til \''.$recipient->name.'\' - '.$recipient->email);
                        endif;
                    endif;
                    if ($sendViaEformidling):
                        $message = Message::factory()->create([
                            'sender_id' => $this->sender->id,
                            'receiver_id' => $recipient->id,
                        ]);
                        $tmpAttachments = $msgAttachments;
                        $tmpAttachments[] = $mainDocument;

                        $message->attachments = $tmpAttachments;
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
                    elseif ($createNewTickets):
                        // Oppretter sak per mottaker og sender meldingen fra Pureservice
                        $newTicket = $ticket->replicate(['id', 'requestNumber', 'userId', 'emailAddress', 'attachments']);
                        if (!$psRecipient = $this->api->findUser($recipient->email, true)):
                            if (!$isUser): // Mottaker er et foretak
                                if (!$user = $recipient->users()->firstWhere('email', $recipient->email)):
                                    $user = $recipient->users()->create([
                                        'firstName' => 'Postmottak',
                                        'lastName' => $recipient->name,
                                        'email' => $recipient->email,
                                    ]);
                                endif;
                                $user->save();
                            endif;
                            $psRecipient = $user->addOrUpdatePS($this->api);
                            unset($user);
                        else: // Bruker som ikke finnes i PS
                            $psRecipient = $recipient->addOrUpdatePS($this->api);
                        endif;
                        $newTicket->userId = $psRecipient->id;
                        $newTicket->visibility = config('pureservice.visibility.no_receipt');
                        $newTicket->statusId = $this->api->findStatus(config('pureservice.ticket.status_in_progress'));
                        $newTicket = $newTicket->addOrUpdatePS($this->api);
                        $this->results['sakerOpprettet'][] = $newTicket;
                    else:
                        // Sendes som e-post
                        if (!$recipient->email):
                            $this->results['skipped']++;
                            $this->error(Tools::L3.'Kan ikke sendes. E-postadresse mangler');
                            continue;
                        endif;
                        Mail::to(Str::replace([' ', ' '], '', $recipient->email))->send(new TicketMessage($ticket, false, $email['subject'], $email['html'], $msgAttachments));
                        $this->line(Tools::L3.'Sendt med e-post');
                        $this->results['email']++;
                    endif;
                endforeach; // $recipients
                if ($autocloseTicket):
                    $solution = Blade::render('report', [
                        'results' => $this->results,
                        'method' => $preferEformidling ? 'eFormidling' : 'e-post',
                        'recipients' => $recipients,
                    ]);
                    $this->api->solveWithAttachment($ticket, $solution, $mainDocument);
                    $this->line(Tools::L2.'Saken har blitt kvittert ut i Pureservice');
                endif;
                // Merker meldingen som sendt i Pureservice
                if ($sent = $this->api->setEmailStatus($email['id'], config('pureservice.email.status.sent'))):
                    $this->line(Tools::L2.'Meldingen ble merket sendt i Pureservice');
                else:
                    $this->error(Tools::L2.'Meldingen kunne ikke merkes som sendt i Pureservice');
                endif;
            endif; // if ($process)
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
