<?php

namespace App\Services;
use App\Services\{API, Enhetsregisteret};
use App\Models\{Message, User, Company, Ticket};
use Illuminate\Support\{Str, Arr, Collection};
use Illuminate\Support\Facades\{Storage};
use ZipArchive;
use Barryvdh\DomPDF\Facade\Pdf as PDF;
use cardinalby\ContentDisposition\ContentDisposition;
use Illuminate\Http\Client\Response;
use PhpParser\Node\Stmt\While_;

class Eformidling extends API {
    protected Enhetsregisteret $brreg;

    public function __construct() {
        parent::__construct();
        $this->brreg = new Enhetsregisteret();
    }

    /**
     * Mulighet til å bytte hvilket integrasjonspunkt som skal brukes
    */
    public function setIP(bool $useTest = false): void {
        if ($useTest):
            config([
                'eformidling.default.api' => config('eformidling.api'),
                'eformidling.api' => config('eformidling.testapi'),
            ]);
        elseif (config('eformidling.default.api', false)):
            config([
                'eformidling.api' => config('eformidling.default.api'),
            ]);
        endif;
        $this->setProperties();
    }


    /**
     * Finner ut hvilke brukerIDer i Pureservice som skal knyttes til sender og receiver
     */
    public function resolveActors(array $message): array {
        $sender = $this->brreg->getCompany(
            $this->stripIsoPrefix(
                Arr::get($message, 'standardBusinessDocumentHeader.sender.0.identifier.value')
            )
        );
        if (!$sender):
            // Avsender slås ikke opp i BRREG
            $sender = Company::factory()->create([
                'organizationNumber' => $this->stripIsoPrefix(
                    Arr::get($message, 'standardBusinessDocumentHeader.sender.0.identifier.value')
                ),
                'name' => 'Virksomhet ikke i BRREG',
                'email' => null,
                'phone' => null,
                'website' => null,
            ]);
            $sender->save();
        endif;
        $receiver = $this->brreg->getCompany(
            $this->stripIsoPrefix(
                Arr::get($message, 'standardBusinessDocumentHeader.receiver.0.identifier.value')));
        return [
            'sender' => $sender ? $sender : $this->stripIsoPrefix(Arr::get($message, 'standardBusinessDocumentHeader.sender.0.identifier.value')),
            'receiver' => $receiver,
        ];
    }

    /**
     * Fjerner prefiksen for orgnumre
     */
    public function stripIsoPrefix(string $identifier): string {
        return Str::after($identifier, ':');
    }

    /**
     * Legger til prefix for orgnr
     */
    public function addIsoPrefix(string $identifier): string {
        // Dersom prefiksen allerede er der, bare send tilbake $identifier urørt
        if (Str::startsWith($identifier, config('eformidling.address.prefix'))):
            return $identifier;
        endif;
        return config('eformidling.address.prefix').$identifier;
    }


    /**
     * Ser etter innkommende meldinger
     */
    public function getIncomingMessages() : Collection|false {
        $params = [
            'size' => 100,
            'page' => 0,
        ];
        $uri = 'messages/in';
        $messages = collect([]);
        $last = false;
        while ($last == false):
            $response = $this->apiQuery($uri, $params, true);
            if ($response->successful() && $response->json('totalElements') > 0):
                //dd($response->body());
                $contents = $response->json('content');
                $params['page']++;
                $last = $response->json('last');
                // Legger til resultatene
                foreach ($contents as $msg):
                    $messages->push($msg);
                endforeach;
            endif;
        endwhile;
        return $messages->count() ? $messages : false;
    }

    /**
     * Returnerer dokumenttypen fra en rå melding
     */
    public function getMessageDocumentType(array $message): string {
        return Arr::get($message, 'standardBusinessDocumentHeader.documentIdentification.type');
    }

    /**
     * Peek låser en innkommende melding og gjør den tilgjengelig for nedlasting
     * @return Illuminate\Http\Client\Response Angir om forespørselen var vellykket eller ikke
     */
    public function peekIncomingMessageById(string $messageId) : Response {
        $uri = 'messages/in/peek';
        $params = ['messageId' => $messageId];
        $result = $this->apiQuery($uri, $params, true);
        return $result;
    }

    public function peekIncomingMessage(): array {
        $uri = 'messages/in/peek';
        return $this->apiGet($uri);
    }

    /**
     * Laster ned innkommende melding sin zip-fil
     */
    public function downloadIncomingAsic(string $messageId, string|false $dlPath = false, bool $returnResponse = false): Response|array|false {
        $dbMessage = Message::firstWhere('messageId', $messageId);
        $dbAttachments = is_array($dbMessage->attachments) ? $dbMessage->attachments : [];
        $uri = 'messages/in/pop/'.$messageId;
        $dlPath = $dlPath ? $dlPath : $this->myConf('download_path') . '/'. $messageId;
        $tmpFile = $dlPath.'/asic.tmp';
        $response = $this->apiGet($uri, true, $this->myConf('api.asic_accept'), null, Storage::path($tmpFile));
        if ($response->successful()):
            // Henter filnavn fra header content-disposition - 'attachment; filename="dokumenter-7104a48e.zip"'
            $cd = ContentDisposition::parse($response->header('content-disposition'));
            $asicName = $dlPath . '/' . $cd->getFileName();
            Storage::move($tmpFile, $asicName);

            // Pakker ut zip-filen
            $path = $dbMessage->downloadPath();
            // Hvis vi har et filnavn som slutter på .zip pakker vi den ut
            if (Str::endsWith($asicName, '.zip')):
                $zipArchive = new ZipArchive();
                $zipArchive->open(Storage::path($asicName), ZipArchive::RDONLY);
                $zipArchive->extractTo(Storage::path($path));
                $zipArchive->close();
                // Sletter zip-filen, siden innholdet er pakket ut
                Storage::delete($asicName);
                if (Storage::directoryExists($path.'/META-INF')):
                    Storage::deleteDirectory($path.'/META-INF');
                endif;
                // Sletter Asic-fila, siden det er en zip-fil
                Storage::delete($asicName);
            endif;
            foreach (Storage::files($path) as $filepath):
                // Hopper over filer med filnavn som begynner med '.'
                $fname = basename($filepath);
                if (Str::startsWith($fname, '.') || $fname == 'manifest.xml' || $fname == 'mimetype'):
                    continue;
                endif;
                // Legger filen til i listen over vedlegg
                $dbAttachments[] = $filepath;
            endforeach;
            $dbMessage->attachments = $dbAttachments;
            $dbMessage->save();
        endif;
        if ($returnResponse) return $response;
        return $dbAttachments;
    }

    public function deleteIncomingMessage(string $messageId): bool {
        $uri = 'messages/in/'.$messageId;
        if ($this->apiDelete($uri)):
            return true;
        endif;
        return false;
    }

    public function getMsgDocumentIdentification(array $msg): array|string {
        return Arr::get($msg, 'standardBusinessDocumentHeader.documentIdentification');
    }

    public function getMsgConversationIdScope(array $msg): array|string {
        $scope = collect(Arr::get($msg, 'standardBusinessDocumentHeader.businessScope.scope'));
        return $scope->firstWhere('type', 'ConversationId');
    }

    /**
     * Lagrer meldingen i databasen
     */
    public function storeIncomingMessage(array $message): Message {
        $conversationIdScope = $this->getMsgConversationIdScope($message);
        $documentIdentification = $this->getMsgDocumentIdentification($message);
        $actors = $this->resolveActors($message);
        //dd($actors);
        if ($dbMessage = Message::find($documentIdentification['instanceIdentifier'])):
            // Meldingen ligger allerede i DB.
            return $dbMessage;
        endif;
        $newMessage = Message::factory()->create([
            'messageId' => $documentIdentification['instanceIdentifier'],
            'sender_id' => $actors['sender']->internal_id,
            'receiver_id' => $actors['receiver']->internal_id,
            'documentStandard' => $documentIdentification['standard'],
            'conversationId' => $conversationIdScope['instanceIdentifier'],
            'documentType' => $documentIdentification['type'],
            'content' => $message,
            'mainDocument' => Arr::get($message, 'arkivmelding.hoveddokument', null),
        ]);
        $newMessage->save();
        return $newMessage;
    }

    /**
     * Laster ned, pakker ut, og knytter vedlegg til meldingen
     */
    // public function processAsic(string $msgId): int|false {
    //     $dbMessage = Message::firstWhere('messageId', $msgId);
    //     $dbAttachments = is_array($dbMessage->attachments) ? $dbMessage->attachments : [];
    //     if (count($dbAttachments) == 0):
    //         // Vi har ikke tidligere lastet ned vedlegg for denne meldingen
    //         $path = $dbMessage->downloadPath();
    //         $wait = true;
    //         $try = 0;
    //         $max_tries = 5;
    //         $zipfile = false;
    //         while ($wait && $try >= $max_tries):
    //             $try++;
    //             if ($zipfile = $this->downloadIncomingAsic($dbMessage->id, $path)):
    //                 $wait = false;
    //             else:
    //                 sleep(3);
    //             endif;
    //         endwhile;
    //         if (!$zipfile) return false;

    //         if (Str::endsWith($zipfile, '.zip')):
    //             // Vi har et filnavn som slutter på .zip, pakker den ut
    //             $zipArchive = new ZipArchive();
    //             $zipArchive->open(Storage::path($zipfile), ZipArchive::RDONLY);
    //             $zipArchive->extractTo(Storage::path($path));
    //             $zipArchive->close();
    //             // Sletter zip-filen, siden innholdet er pakket ut
    //             Storage::delete($zipfile);
    //             if (Storage::directoryExists($path.'/META-INF')):
    //                 Storage::deleteDirectory($path.'/META-INF');
    //             endif;
    //         endif;
    //         foreach (Storage::files($path) as $filepath):
    //             // Hopper over filer med filnavn som begynner med '.'
    //             $fname = Str::afterLast($filepath, '/');
    //             if (Str::startsWith($fname, '.') || $fname == 'manifest.xml' || $fname == 'mimetype'):
    //                 continue;
    //             endif;

    //             // Legger filen til i listen over vedlegg
    //             $dbAttachments[] = $filepath;
    //         endforeach;
    //         $dbMessage->attachments = $dbAttachments;
    //         $dbMessage->save();
    //         return count($dbMessage->attachments);
    //     else:
    //         return count($dbMessage->attachments);
    //     endif;

    // }

    // Returnerer array over meldingstyper som foretaket kan motta
    public function getCapabilities(Company $org, $filter = true) : array {
        $types = [];
        $uri = 'capabilities/'.$org->organizationNumber;
        if ($result = $this->apiGet($uri)):
            if (count($result['capabilities'])):
                $capabilities = collect($result['capabilities']);
                if ($filter):
                    $capabilities->each(function($c) use ($types) {
                        $process = '';
                    });
                else:
                    $types = $capabilities->all();
                endif;
            endif;
        endif;
        return $types;
    }

    /**
     * Sender eFormidlings-meldingen til integrasjonspunktet
     * - Oppretter arkivmelding på integrasjonspunktet
     * - Laster opp vedlegg til integrasjonspunktet
     * - Sender meldingen
     */
    public function sendMessage(Message $m, bool $test = false): bool {
        $this->setIP($test);
        if ($created = $this->createArkivmelding($m)):
            $files = $this->uploadAttachments($m);
        endif;
        if ($created):
            return $this->dispatchMessage($m);
        endif;
        return false;
    }

    public function createArkivmelding(Message $m): bool {
        $uri = 'messages/out';
        $body = $m->content;
        $result = $this->apiPost($uri, $body);
        if (!$result->successful()):
            return false;
        else:
            return true;
        endif;

        return false;
    }

    public function uploadAttachments(Message $m): array {
        $uri = 'messages/out/'.$m->messageId;
        $results = ['count' => 0];
        foreach ($m->attachments as $file):
            if (Storage::exists($file)):
                $request = $this->prepRequest('application/json', Storage::mimeType($file));
                $request->withHeader('Content-Disposition', ContentDisposition::create(basename($file))->format());
                if (basename($file) != 'arkivmelding.xml'):
                    $request->withBody(Storage::readStream($file), Storage::mimeType($file));
                else:
                    $request->withBody(Storage::get($file), Storage::mimeType($file));
                endif;
                $result = $request->put($uri);
                $results[$file] = $result->successful();
                $results['count']++;
                //$result = $this->apiPost($uri, $stream, 'application/json', Storage::mimeType($file));
            endif;
            $results[$file] = false;
        endforeach;
        return $results;
    }

    /**
     * Sender en opprettet melding til mottaker
     */
    public function dispatchMessage(Message $m): bool {
        $uri = 'messages/out/'.$m->messageId;
        $response = $this->apiPost($uri, null);
        return $response->successful();
    }


    // public function createAndSendMessage(Ticket $ticket, Company $recipient) {
    //     if (!$sender = Company::firstWhere('organizationNumber', config('eformidling.address.sender_id'))):
    //         $sender = Company::factory()->create([
    //             'name' => config('eformidling.address.sender_name'),
    //             'organizationNumber' => config('eformidling.address.sender_id'),
    //         ]);
    //     endif;
    //     $message = Message::factory()->create([
    //         'recipient_id' => $recipient->internal_id,
    //         'sender_id' => $sender->internal_id,
    //     ]);
    //     $mainFile = $ticket->makePdf('message', 'melding.pdf');
    //     $recipientCapabilities = collect($this->getCapabilities($recipient));
    // }
}
