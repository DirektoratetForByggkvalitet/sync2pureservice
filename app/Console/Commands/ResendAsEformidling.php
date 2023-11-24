<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\{Arr, Str, Collection};
use Illuminate\Support\Facades\{Storage};
use App\Services\{Eformidling, PsApi, Tools};
use App\Models\{Message, Company};
use Barryvdh\DomPDF\Facade\Pdf as PDF;

class ResendAsEformidling extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'pureservice:resend-eformidling-messages';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sjekker Pureservice for utgående meldinger som skal ut via eFormidling, og sender dem';
    protected $version = '1.0';
    protected float $start;
    protected Collection $messages;
    protected PsApi $api;
    protected Eformidling $ef;
    protected Company $sender;
    /**
     * Execute the console command.
     */
    public function handle(): int {
        $this->start = microtime(true);
        $this->info(class_basename($this).' v'.$this->version);
        $this->line($this->description);
        $this->newLine(2);

        $this->api = new PsApi();

        $uri = '/email/';
        $params = [
            'filter' => 'status == 5',
            'sort' => 'created DESC',
            'include' => 'attachments',
        ];

        $response = $this->api->apiQuery($uri, $params, true);
        if ($response->successful()):
            $messages = collect($response->json('emails'));
            if ($messages->count() == 0):
                $this->info(Tools::L1.'Ingen meldinger ble funnet.');
                return Command::SUCCESS;
            endif;
            $this->messages = $messages->filter(function (array $item, int $key) {
                return Str::endsWith($item['to'], config('pureservice.user.ef_domain'));
            });
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
                $toRegNo = Str::before($email['to'], '@');
                $receiver = $this->api->findCompany($toRegNo, null, true);
                $receiver->save();
                $this->line(Tools::L2.'Behandler melding til \''.$receiver->name.'\' - '.$receiver->organizationNumber);
                $attachmentIds = $email['links']['attachments']['ids'];
                $message = Message::factory()->create([
                    'sender_id' => $this->sender->id,
                    'receiver_id' => $receiver->id,
                ]);
                $dlPath = $message->downloadPath();
                $mainDocument = $dlPath.'/melding.pdf';
                if (count($attachmentIds)):
                    $msgAttachments = $this->api->downloadAttachmentsById($attachmentIds, $dlPath);
                endif;
                if (!isset($msgAttachments)) $msgAttachments = [];
                $msgAttachments[] = $mainDocument;
                PDF::loadHTML($email['html'])->save(Storage::path($mainDocument));
                $message->attachments = $msgAttachments;
                $message->createContent();
                $message->setMainDocument($mainDocument);
                $ticket = $this->api->getTicketFromPureservice($email['requestId'], false);
                $message->createXmlFromTicket($ticket);
                $sent = $this->ef->sendMessage($message);
            });

        else:
            $this->error('Feil ved innhenting av forsendelser');
            return Command::FAILURE;
        endif;

        return Command::SUCCESS;
    }
}
