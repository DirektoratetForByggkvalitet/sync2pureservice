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
        $this->setOptions($options);
    }

    /**
     * Finner ut hvilke brukerIDer i Pureservice som skal knyttes til sender og receiver
     */
    public function resolveActors(array $message): array {
        return [
            'sender' => $this->brreg->getCompany(
                $this->stripIsoPrefix(
                    Arr::get($message, 'standardBusinessDocumentHeader.sender.0.identifier.value'))),
            'recevier' => $this->brreg->getCompany(
                $this->stripIsoPrefix(
                    Arr::get($message, 'standardBusinessDocumentHeader.receiver.0.identifier.value'))),
        ];
    }
    /**
     * Fjerner prefiksen for orgnumre
     */
    public function stripIsoPrefix(string $identifier): string {
        return Str::after($identifier, ':');
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
        $uri = 'messages/in/peek';
        if ($result = $this->apiGet($uri, false, null, ['messageId' => $messageId]))
            return $result;
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
            $fileMimeType = Str::before($response->header('content-type'), ';');
            $cd = ContentDisposition::parse($response->header('content-disposition'));
            $fileName = $dlPath.'/'. $cd->getFileName();
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

    /**
     * Lagrer meldingen i databasen
     */
    public function storeMessage(array $message): bool {
        $conversationIdScope = $this->getMsgConversationIdScope($message);
        $documentIdentification = $this->getMsgDocumentIdentification($message);
        $actors = $this->resolveActors($message);
        $newMessage = Message::factory()->create([
            'sender_id' => $actors['sender']->internal_id,
            'receiver_id' => $actors['receiver']->internal_id,
            'documentId' => $documentIdentification['instanceIdentifier'],
            'documentStandard' => $documentIdentification['standard'],
            'conversationId' => $conversationIdScope['instanceIdentifier'],
            'conversationIdentifier' => $conversationIdScope['identifier'],
            'content' => $message,
            'mainDocument' => Arr::get($message, 'arkivmelding.hoveddokument'),
            'attachments' => [],
        ]);
        $newMessage->save();
        return true;
    }

    public function getMsgDocumentIdentification(array $msg): string {
        return Arr::get($msg, 'standardBusinessDocumentHeader.documentIdentification');
    }

    public function getMsgConversationIdScope(array $msg): string {
        $scope = collect(Arr::get($msg, 'standardBusinessDocumentHeader.businessScope.scope'));
        return $scope->firstWhere('type', 'ConversationId');
    }

    /**
     * Laster ned, pakker ut, og knytter vedlegg til meldingen
     */
    public function storeAttachments(array $message): bool {
        // Henter inn meldingen fra DB
        $msgIdentification = $this->getMsgDocumentIdentification($message);
        $dbMessage = Message::firstWhere('messageId', $msgIdentification['instanceIdentifier']);
        $path = $dbMessage->downloadPath();
        $zipfile = $this->downloadIncomingAsic($dbMessage->messageId, $path);
        if (!$zipfile) return false;

        if (Str::endsWith($zipfile, '.zip')):
            // Vi har et filnavn som slutter på .zip, pakker den ut
            $zipArchive = new ZipArchive();
            $zipArchive->open(Storage::path($zipfile), ZipArchive::RDONLY);
            $zipArchive->extractTo($path);
            $zipArchive->close();
            // Sletter zip-filen, siden innholdet er pakket ut
            Storage::delete($zipfile);
        endif;
        foreach (Storage::files($path) as $file):
            // Hopper over filer med filnavn som begynner med '.'
            if (Str::startsWith(Str::afterLast($file, '/'), '.')) continue;
            // Legger filen til i listen over vedlegg
            $dbMessage->attachments[] = $file;
        endforeach;
        $dbMessage->save();
        return true;
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

    public function createAndSendMessage(Ticket $ticket, Company $recipient) {
        $recipientIdentifier = $this->myConf('address.prefix'). $recipient->organizationNumber;
        $mainFile = $ticket->makePdf('message', 'melding.pdf');
        $recipientCapabilities = collect($this->getCapabilities($recipient));
    }
}
