<?php

namespace App\Services;
use App\Services\{API, Enhetsregisteret};
use App\Models\{Message, User, Company};
use Illuminate\Support\{Str, Arr};
use ZipArchive;


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
     * Ser etter innkommende meldinger
     */
    public function getIncomingMessages($filters = []) : array|false {
        $args = '';
        $filters['size'] = 100;
        if (count($filters)):
            //$args .='?';
            $i = 1;
            foreach($filters as $key => $value):
                // Legger ikke til & hvis det er første argument
                $args .= $i == 0 ? '' : '&';
                $i++;
                $args .= $key . '=' . $value;
            endforeach;
        endif;
        $uri = 'messages/in';
        if ($response = $this->apiGet($uri)):
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
    public function downloadIncomingAsic(string $messageId, string|false $path = false): string|false {
        $uri = 'messages/in/pop/'.$messageId;
        $path = $path ? $path : $this->myConf('download_path') . '/'. $messageId;
        if ($response = $this->apiGet($uri, true, $this->myConf('api.asic_accept'))->toPsrResponse()):
            // Henter filnavn fra header content-disposition - 'attachment; filename="dokumenter-7104a48e.zip"'
            $fileName = preg_replace('/.*\"(.*)"/','$1', $response->getHeader('content-disposition')[0]);
            // Oppretter $path hvis den ikke finnes
            if (!is_dir($path)):
                mkdir($path, 0770, true);
            endif;
            file_put_contents($path.'/'.$fileName, $response->getBody()->getContents());
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
        $newMessage = Message::factory()->create([
            'sender' => '',
            'receiver' => '',
            'documentId' => $documentIdentification['instanceIdentifier'],
            'documentStandard' => $documentIdentification['standard'],
            'conversationId' => $conversationIdScope['instanceIdentifier'],
            'conversationIdentifier' => $conversationIdScope['identifier'],
            'content' => $message,
            'mainDocument' => Arr::get($message, 'arkivmelding.hoveddokument'),
            'attachments' => null,
        ]);
        $messageSender = '';
        foreach (Arr::get($message, 'standardBusinessDocumentHeader.sender') as $id):
            $idValue = Arr::get($id, 'identifier.value');
            $messageSender .= $messageSender == '' ? $idValue : ', ' . $idValue;
            if (Str::contains($idValue, '0192:') && $c = $this->brreg->getCompany(Str::after($idValue, '0192:'))):
                $newMessage->companies()->attach($c->internal_id, ['type' => 'sender']);
            endif;
        endforeach;

        $messageReceiver = '';
        foreach(Arr::get($message, 'standardBusinessDocumentHeader.receiver') as $id):
            $idValue = Arr::get($id, 'identifier.value');
            $messageReceiver .= $messageReceiver == '' ? $idValue : ', ' . $idValue;
            if (Str::contains($idValue, '0192:') && $c = $this->brreg->getCompany(Str::after($idValue, '0192:'))):
                $newMessage->companies()->attach($c->internal_id, ['type' => 'receiver']);
            endif;
        endforeach;
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
        $path = $this->myConf('download_path') . '/'. $dbMessage->messageId;
        $filesToAttach = [];
        $fileName = $this->downloadIncomingAsic($dbMessage->messageId);
        if (!$fileName) return false;

        // Vi har et filnavn, pakker den ut
        $zipFile = new ZipArchive();
        $zipFile->open($path.'/'.$fileName, ZipArchive::RDONLY);
        $zipFile->extractTo($path);
        $zipFile->close();
        // Sletter zip-filen
        unlink($path.'/'.$fileName);
        foreach (new \DirectoryIterator($path) as $fileInfo):
            if($fileInfo->isDot()) continue;
            $filesToAttach[] = $path.'/'.$fileInfo->getFilename();
        endforeach;
        $dbMessage->attachments = $filesToAttach;
        $dbMessage->save();
        return true;
    }

}
