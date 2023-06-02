<?php

namespace App\Services;
use App\Services\{API, Enhetsregisteret};
use App\Models\{Message, User, Company};
use Illuminate\Support\{Str, Arr};


class NextMove extends API {
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
        $uri = $this->resolveUri('/messages/in'.$args);
        $response = $this->apiGet($uri);
        dd($response);
        //if ($result = $this->apiGet($uri, true))
        //    return json_decode($result->getBody()->getContents(), true);
        return false;
    }

    /**
     * Peek låser en innkommende melding og gjør den tilgjengelig for nedlasting
     */
    public function peekIncomingMessageById(string $messageId) : array|false {
        $uri = '/messages/in/peek?messageId='.$messageId;
        if ($result = $this->apiGet($uri))
            return $result;
        return false;
    }

    /**
     * Laster ned innkommende melding sin zip-fil
     */
    public function downloadIncomingAsic(string $messageId, string|false $path = false): string|false {
        $uri = '/messages/in/pop/'.$messageId;
        if ($response = $this->apiGet($uri, true, $this->myConf('api.asic_accept'))):
            // Henter filnavn fra header content-disposition - 'attachment; filename="dokumenter-7104a48e.zip"'
            $fileName = preg_replace('/.*\"(.*)"/','$1', $response->getHeader('content-disposition')[0]);
            if (!$path):
                $path = $this->myConf('download_path');
            endif;
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
        $uri = '/messages/in/'.$messageId;
        if ($this->apiDelete($uri)):
            return true;
        endif;
        return false;
    }

    public function storeMessage(array $message, array $attachments): void {
        $messageSender = '';
        $senderCompanies = [];
        foreach (Arr::get($message, 'standardBusinessDocumentHeader.sender') as $id):
            $idValue = Arr::get($id, 'identifier.value');
            $messageSender .= $messageSender == '' ? $idValue : ', ' . $idValue;
            if (Str::contains($idValue, '0192:')):
                $senderCompanies[] = $this->brreg->getAndStoreCompany(Str::after($idValue, '0192:'));
            endif;
        endforeach;

        $messageReceiver = '';
        $receiverCompanies = [];
        foreach(Arr::get($message, 'standardBusinessDocumentHeader.receiver') as $id):
            $idValue = Arr::get($id, 'identifier.value');
            $messageReceiver .= $messageReceiver == '' ? $idValue : ', ' . $idValue;
            if (Str::contains($idValue, '0192:')):
                $receiverCompanies[] = $this->brreg->getAndStoreCompany(Str::after($idValue, '0192:'));
            endif;
        endforeach;


    }
}
