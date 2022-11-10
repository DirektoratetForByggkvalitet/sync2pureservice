<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Http\Controllers\{PureserviceController, SvarInnController};
use Carbon\Carbon;
use GuzzleHttp\Client as GuzzleClient;

class SvarInn2Pureservice extends Command
{
    protected $l1 = '';
    protected $l2 = '> ';
    protected $l3 = '  ';
    protected $start;
    protected $pureservice;
    protected $svarInn;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'svarinn2pureservice:run';

    protected $version = '1.0';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Henter innkommende meldinger fra SvarInn, og melder dem som saker i Pureservice';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle() {
        $this->start = microtime(true);
        $this->info('SvarInn2Pureservice v'.$this->version);
        $this->line($this->description);
        $this->line('');

        $this->info($this->ts().'Kobler til SvarInn');
        $this->svarInn = new SvarInnController();

        $this->info($this->ts().'Kobler til Pureservice');
        $this->pureservice = new PureserviceController();

        $this->info($this->ts().'Ser etter nye meldinger i SvarInn');
        $msgs = $this->svarInn->sjekkForMeldinger();
        $msgCount = count($msgs);
        $i = 0;
        if ($msgCount > 0):
            $this->line($this->l3.'Fant '.$msgCount.' melding(er)');
            foreach ($msgs as $message):
                $i++;
                $message = collect($message);
                $this->info($this->l2.$i.'/'.$msgCount.': '.$message['tittel']);
                $this->line($this->l3.'ID: '.$message['id']);
                $this->line($this->l3.'Avsender: '.$message['avsender.navn'].', '.$message['avsender.poststed']);
                $this->line($this->l3.'Laster ned forsendelsesfilen');

            endforeach;
        else:
            $this->line($this->l2.'Ingen meldinger å hente');
        endif;

        return Command::SUCCESS;
    }
    /**
     * Returnerer formatert tidspunkt til logging
     */
    protected function ts() {
        return '['.Carbon::now(config('app.timezone'))->toDateTimeLocalString().'] ';
    }

    /**
     * Henter ned forsendelsesfilen som er oppgitt i meldingen
     */
    protected function hentForsendelsefil($uri) {
        $options = [
            'headers' => [
                'Accept-Encoding' => 'gzip, deflate, br',
                'Accept' => '*/*'
            ],
            'stream' => true,
            'auth' => [
                config('svarinn.username'),
                config('svarinn.secret')
            ]
        ];
        $api = new GuzzleClient([
            'allow_redirects' => true,
        ]);

        $fileResponse = $api->get($uri, $options);

        $contentType = $fileResponse->getHeader('content-type');
        // Henter filnavn fra header content-disposition - 'attachment; filename="dokumenter-7104a48e.zip"'
        $fileName = preg_replace('/.*\"(.*)"/','$1', $fileResponse->getHeader('content-disposition'));
        $this->line($this->l3.'Dekrypterer forsendelsesfila');
    }

    /**
     * Dekrypterer mottatt fil med privatnøkkelen.
     */
    protected function decryptFile($encrypted) {
        $privateKey = config('svarinn.private_key');
        $decrypted = null;
        $algo = 'aes-256-cbc';
        // Se https://github.com/dwgebler/php-encryption for å endre
        openssl_private_decrypt($encrypted, $decrypted, $privateKey);
        return $decrypted;
    }

}
