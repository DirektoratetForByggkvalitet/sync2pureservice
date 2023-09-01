<?php

namespace App\Services;
use App\Services\{API, Enhetsregisteret};
use App\Models\{Message, User, Company, Ticket};
use Illuminate\Support\{Str, Arr};
use Illuminate\Support\Facades\{Storage};
use ZipArchive;
use Barryvdh\DomPDF\Facade\Pdf as PDF;
use cardinalby\ContentDisposition\ContentDisposition;


class Eformidling extends API {
    protected Enhetsregisteret $brreg;

    public function __construct() {
        parent::__construct();
        $this->prefix = $this->myConf('api.prefix');
        $this->setupClient();
        $this->brreg = new Enhetsregisteret();
    }

    protected function setupClient(): void {
        $options = [];
        $options['headers'] = [
            'Accept' => 'application/json',
            'Connection' => 'keep-alive',
            'Accept-Encoding' => 'gzip, deflate, br',
            'User-Agent' => 'sync2pureservice/PHP'
        ];
        // Legger til innlogging, hvis påkrevd
        if ($this->myConf('api.auth') == true):
            $options['auth'] = [
                $this->myConf('api.user'),
                $this->myConf('api.password'),
            ];
        endif;
        $this->base_url = $this->myConf('api.url');
        $this->setOptions($options);
    }
    /**
     * Setter opp til å bruke Digdir sitt test-api for å sende meldinger (til oss selv)
    */
    public function switchToTestIp(): void {
        config([
            'eformidling.default.api' => config('eformidling.api'),
            'eformidling.api' => config('eformidling.testapi'),
        ]);
        $this->setupClient();
    }

    public function switchToOriginalIp(): void {
        if (config('eformidling.default.api', false)):
            config([
                'eformidling.api' => config('eformidling.default.api'),
            ]);
            $this->setupClient();
        endif;
    }

    /**
     * Finner ut hvilke brukerIDer i Pureservice som skal knyttes til sender og receiver
     */
    public function resolveActors(array $message): array {
        $sender = $this->brreg->getCompany(
            $this->stripIsoPrefix(
                Arr::get($message, 'standardBusinessDocumentHeader.sender.0.identifier.value')));
        $receiver = $this->brreg->getCompany(
            $this->stripIsoPrefix(
                Arr::get($message, 'standardBusinessDocumentHeader.receiver.0.identifier.value')));
        return [
            'sender' => $sender ? $sender : Arr::get($message, 'standardBusinessDocumentHeader.sender.0.identifier.value'),
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
    public function getIncomingMessages($filters = []) : array|false {
        $filters['size'] = isset($filters['size']) ? $filters['size'] : 100;
        $uri = 'messages/in';
        if ($response = $this->apiQuery($uri, $filters)):
            return $response['content'];
        endif;

        return false;
    }

    /**
     * Peek låser en innkommende melding og gjør den tilgjengelig for nedlasting
     */
    public function peekIncomingMessageById(string $messageId) : array|false {
        $uri = 'messages/in/peek?messageId='.$messageId;
        if ($result = $this->apiGet($uri)):
            return $result != null ? $result : false;
        endif;
        return false;
    }

    /**
     * Laster ned innkommende melding sin zip-fil
     */
    public function downloadIncomingAsic(string $messageId, string|false $dlPath = false): string|false {
        $uri = 'messages/in/pop/'.$messageId;
        $dlPath = $dlPath ? $dlPath : $this->myConf('download_path') . '/'. $messageId;
        if ($response = $this->apiGet($uri, true, $this->myConf('api.asic_accept'))):
            // Henter filnavn fra header content-disposition - 'attachment; filename="dokumenter-7104a48e.zip"'
            //$fileMimeType = Str::before($response->header('content-type'), ';');
            $cd = ContentDisposition::parse($response->header('content-disposition'));
            $fileName = $dlPath . '/' . $cd->getFileName();
            Storage::put(
                $fileName,
                $response->toPsrResponse()->getBody()->getContents()
            );
            return $fileName;
        endif;
        return false;
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
            'attachments' => [],
        ]);
        $newMessage->save();
        return $newMessage;
    }

    /**
     * Laster ned, pakker ut, og knytter vedlegg til meldingen
     */
    public function downloadMessageAttachments(string $msgId): int|false {
        $dbMessage = Message::firstWhere('messageId', $msgId);
        $dbAttachments = is_array($dbMessage->attachments) ? $dbMessage->attachments : [];
        if (count($dbAttachments) == 0):
            // Vi har ikke tidligere lastet ned vedlegg for denne meldingen
            $dbMessage->save();
            $path = $dbMessage->downloadPath();
            $zipfile = $this->downloadIncomingAsic($dbMessage->id, $path);
            if (!$zipfile) return false;

            if (Str::endsWith($zipfile, '.zip')):
                // Vi har et filnavn som slutter på .zip, pakker den ut
                $zipArchive = new ZipArchive();
                $zipArchive->open(Storage::path($zipfile), ZipArchive::RDONLY);
                $zipArchive->extractTo(Storage::path($path));
                $zipArchive->close();
                // Sletter zip-filen, siden innholdet er pakket ut
                Storage::delete($zipfile);
                if (Storage::directoryExists($path.'/META-INF')):
                    Storage::deleteDirectory($path.'/META-INF');
                endif;
            endif;
            foreach (Storage::files($path) as $filepath):
                // Hopper over filer med filnavn som begynner med '.'
                $fname = Str::afterLast($filepath, '/');
                if (Str::startsWith($fname, '.') || $fname == 'manifest.xml' || $fname == 'mimetype'):
                    continue;
                endif;

                // Legger filen til i listen over vedlegg
                $dbAttachments[] = $filepath;
            endforeach;
            $dbMessage->attachments = $dbAttachments;
            $dbMessage->save();
            return count($dbMessage->attachments);
        else:
            return count($dbMessage->attachments);
        endif;

    }

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
     * Sender eFormidlings-meldingen til QA-integrasjonspunktet hos Digdir
     */
    public function sendMessageWithTest(Message $message): bool {
        $this->switchToTestIp();
        // Bytter om sender og mottaker, slik at
        if ($created = $this->createArkivmelding($message)):
            $files = $this->uploadAttachments($message);
        endif;
        if ($created):
            $this->sendMessage($message);
        endif;
        $this->switchToOriginalIp();
        return false;
    }

    public function createArkivmelding(Message $message): bool {
        $uri = 'messages/out';
        $body = $message->content;
        $result = $this->apiPost($uri, $body);
        if ($result->failed()):
            return false;
        else:
            return true;
        endif;

        return false;
    }

    public function uploadAttachments(Message $message): array {
        $uri = 'messages/out/'.$message->id;
        $results = ['count' => 0];
        foreach ($message->attachments as $file):
            if (Storage::exists($file)):
                $request = $this->prepRequest('application/json', Storage::mimeType($file));
                $request->withHeader('Content-Disposition', ContentDisposition::create(basename($file)));
                $request->withBody(base64_encode(file_get_contents(Storage::path($file))), Storage::mimeType($file));
                $result = $request->put($uri);
                $results[$file] = $result->sucessful();
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
    public function sendMessage(Message $message): bool {
        $uri = 'messages/out/'.$message->id;
        $response = $this->apiPost($uri);
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
