<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Http\Controllers\{PureserviceController, SvarInnController};
use Carbon\Carbon;
use GuzzleHttp\Client as GuzzleClient;
use Gebler\Encryption\Encryption;
use JetBrains\PhpStorm\Pure;
use ZipArchive;

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
        $this->info(get_class($this).' v'.$this->version);
        $this->line($this->description);

        $this->info($this->ts().'Setter opp miljøet...');
        $this->setConfig();
        $this->line('');

        $this->info($this->ts().'Kobler til SvarInn');
        $this->svarInn = new SvarInnController();

        $this->ps = new PureserviceController();

        $this->info($this->l2.'Ser etter nye meldinger i SvarInn');
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
                $this->line($this->l3.'Avsender: '.$message['svarSendesTil.navn'].', '.$message['svarSendesTil.orgnr']);
                if ($companyInfo = $this->ps->findCompany($message['svarSendesTil.orgnr'], $message['svarSendesTil.navn'])):
                    $this->line($this->l3.'Foretaket er registrert i Pureservice');
                else:
                    $this->line($this->l3.'Foretaket er ikke registrert i Pureservice, legger det til');
                    $companyInfo = $this->ps->addCompany($message['svarSendesTil.orgnr'], $message['svarSendesTil.navn']);
                endif;
                $this->line($this->l3.'Laster ned forsendelsesfilen');
                $fileName = $this->hentForsendelsefil($message['downloadUrl']);
                $this->line($this->l3.'Dekrypterer forsendelsesfila');
                $decrypted = $this->decryptFile($fileName);
                $fileEnding = preg_replace('/.*\.(.*)/', '$1', $fileName);
                $filesToInclude = [];
                if ( $fileEnding == 'zip' || $fileEnding == 'ZIP' ):
                    // Må pakke ut zip-fil til enkeltfiler
                    $this->line($this->l3.'Pakker ut zip-fil');
                    $tmpPath = config('svarinn.temp_path').'/'.$message['id'];
                    mkdir($tmpPath, 770, true);
                    $zipFile = new ZipArchive();
                    $zipFile->open(config('svarinn.dekrypt_path').'/'.$fileName, ZipArchive::RDONLY);
                    $zipFile->extractTo($tmpPath);
                    $zipFile->close();
                    foreach (new \DirectoryIterator($tmpPath) as $fileInfo):
                        if($fileInfo->isDot()) continue;
                        $filesToInclude[] = $tmpPath.'/'.$fileInfo->getFilename();
                    endforeach;
                else:
                    $filesToInclude[] = config('svarinn.dekrypt_path').'/'.$fileName;
                endif;
                $this->line($this->l3.'Lastet ned og/eller pakket ut '.count($filesToInclude).' fil(er)');

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
     * Utvider config til å inkludere svarut-dekrypter sitt oppsett
     */
    protected function setConfig() {
        if (config('svarinn.temp_path') == null):
            config([
                'svarinn.temp_path' == storage_path('/svarinn_tmp')
            ]);
        endif;
        if (config('svarinn.download_path') == null):
            config([
                'svarinn.download_path' => storage_path('/downloads'),
            ]);
        endif;
        if (config('svarinn.dekrypt_path') == null):
            config([
                'svarinn.dekrypt_path' => storage_path('/dekryptert'),
            ]);
        endif;
        if (config('svarinn.dekrypter.jar') == null):
            config([
                'svarinn.dekrypter.jar' => base_path('/dekrypter-'.config('svarinn.dekrypter.version').'/dekrypter-'.config('svarinn.dekrypter.version').'.jar')
            ]);
        endif;
        if (config('svarinn.privatekey_path') == null):
            config([
                'svarinn.privatekey_path' => base_path('/keys/privatekey.pem')
            ]);
        endif;
        // Sikrer at mapper finnes
        is_dir(config('svarinn.temp_path')) ? true : mkdir(config('svarinn.temp_path', 770, true));
        is_dir(config('svarinn.dekrypt_path')) ? true : mkdir(config('svarinn.dekrypt_path', 770, true));
        is_dir(config('svarinn.download_path')) ? true : mkdir(config('svarinn.download_path', 770, true));
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

        file_put_contents(config('svarinn.download_path').'/'.$fileName, $fileResponse->getBody()->getContents());

        return $fileName;
    }

    /**
     * Dekrypterer fil fra SvarUt
     * Vi må bruke et java-bibliotek for å dekryptere filer fra SvarUt,
     * Bouncycastle gjør ting på litt andre måter enn det PHP er i stand til
     *
     * @param  string   $fileName    Filnavnet til den krypterte fila
     * @return int      $returnValue Verdi som angir resultatkoden fra dekrypteringen
     */
    protected function decryptFile($fileName) {
        if (file_exists(config('svarinn.downloadpath').'/'.$fileName)):
            $dekrypt = system(
                'java -jar ' . config('svarinn.dekrypter.jar').' -k ' . config('svarinn.privatekey_path').
                ' -s ' . config('svarinn.download_path').'/'.$fileName .
                ' -t '.config('svarinn.dekrypt_path'),
                $exitCode
            );
            if ($exitCode != 0):
                $this->error($this->l3.'Fila ble ikke dekryptert');
                $this->line($this->l3.$dekrypt);
            endif;
        else:
            $this->error($this->l3.'Fant ikke fila som skulle dekrypteres');
            $exitCode = 2;
        endif;
        return $exitCode;
    }
}
