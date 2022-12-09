<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Http\Controllers\{PureserviceController, SvarInnController, ExcelLookup};
use Carbon\Carbon;
use GuzzleHttp\Client as GuzzleClient;
use ZipArchive;
use Illuminate\Support\{Arr, Str};

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
        $this->info((new \ReflectionClass($this))->getShortName().' v'.$this->version);
        $this->line($this->description);

        $this->info($this->ts().'Setter opp miljøet...');
        $this->setConfig();
        $this->line('');

        $this->info($this->ts().'Kobler til SvarInn');
        $this->svarInn = new SvarInnController();

        $this->ps = new PureserviceController();
        $this->ps->setTicketOptions();

        if (config('svarinn.dryrun') == false || config('svarinn.dryrun') === true):
            $this->info($this->l2.'Ser etter nye meldinger i SvarInn');
            $msgs = $this->svarInn->sjekkForMeldinger();
        else:
            $msgs = json_decode(file_get_contents(storage_path(config('svarinn.dryrun'))), true);
        endif;
        /** Testing: Hent data fra eksempelfil */

        $msgCount = count($msgs);
        $i = 0;
        if ($msgCount > 0):
            $this->line($this->l3.'Fant '.$msgCount.' melding(er)');
            foreach ($msgs as $message):
                $i++;

                $this->info($this->l2.$i.'/'.$msgCount.': '.$message['tittel']);
                $this->line($this->l3.'ID: '.$message['id']);
                $this->line($this->l3.'Tittel: '.$message['tittel']);
                $this->line($this->l3.'Avsender: \''. Arr::get($message, 'svarSendesTil.navn') .'\', '.Arr::get($message, 'svarSendesTil.orgnr'));
                // Henter e-postadresse, dersom vi har den i Excel-fila
                $email = false;
                if ($fileOrg = ExcelLookup::findByName(Str::upper(Arr::get($message, 'svarSendesTil.navn')))):
                    $email = $fileOrg[config('excellookup.field.email')];
                endif;
                if ($companyInfo = $this->ps->findCompany(Arr::get($message,'svarSendesTil.orgnr'), Arr::get($message, 'svarSendesTil.navn'))):
                    $this->line($this->l3.'Foretaket er registrert i Pureservice');
                else:
                    $this->line($this->l3.'Foretaket er ikke registrert i Pureservice, legger det til');
                    $companyInfo = $this->ps->addCompany(Arr::get($message,'svarSendesTil.navn'), Arr::get($message,'svarSendesTil.orgnr'), $email);
                endif;
                if ($email === false):
                    /**
                     * Hvordan løse problematikk når e-postadresse ikke finnes?
                     * Vi oppretter en falsk e-postadresse basert på orgnr. som brukes til å lage bruker.
                     */
                    $email = Arr::get($message,'svarSendesTil.orgnr').'.no_email@dibk.pureservice.com';
                endif;
                if ($userInfo = $this->ps->findUser($email)):
                    $this->line($this->l3.'Foretaksbruker er registrert i Pureservice');
                else:
                    $this->line($this->l3.'Foretaksbruker med e-post '.$email.' er ikke registrert i Pureservice');
                    if ($userInfo = $this->ps->addCompanyUser($companyInfo, $email)):
                        $this->line($this->l3.'Foretaksbruker opprettet');
                    else:
                        $this->error($this->l3.'Foretaksbruker ble ikke opprettet');
                    endif;
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
                    mkdir($tmpPath, 0770, true);
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


                if ($ticket = $this->ps->createTicketFromSvarUt($message, $userInfo)):
                    $this->line($this->l3.'Opprettet i Pureservice med Sak-ID '.$ticket['requestNumber']);

                    if ($result = $this->ps->uploadAttachments($filesToInclude, $ticket, $message)):
                        $this->line($this->l3.'Lastet opp vedlegg');
                    else:
                        $this->error($this->l3.'Vedleggene ble ikke lastet opp');
                    endif;

                    if (config('svarinn.dryrun') == false):
                        if ($this->kvitterForMottak($message['id'])):
                            $this->line($this->l3.'Forsendelsen er kvittert mottatt hos KS');
                        else:
                            $this->error($this->l3.'Forsendelsen kunne ikke settes som mottatt');
                        endif;
                    endif;

                else:
                    $this->error($this->l3.'Feil under oppretting av sak i Pureservice');
                    if (config('svarinn.dryrun') == false) $this->forsendelseFeilet($message['id']);
                endif;

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
        if (config('svarinn.dekrypter.jar') == null):
            config([
                'svarinn.dekrypter.jar' => base_path('dekrypter-'.config('svarinn.dekrypter.version').'/dekrypter-'.config('svarinn.dekrypter.version').'.jar')
            ]);
        endif;

        // Sikrer at mapper finnes
        is_dir(config('svarinn.temp_path')) ? true : mkdir(config('svarinn.temp_path'), 0770, true);
        is_dir(config('svarinn.dekrypt_path')) ? true : mkdir(config('svarinn.dekrypt_path'), 0770, true);
        is_dir(config('svarinn.download_path')) ? true : mkdir(config('svarinn.download_path'), 0770, true);
    }

    protected function getOptions() {
        return [
            'headers' => [
                'Accept-Encoding' => 'gzip, deflate, br',
                'Accept' => '*/*',
            ],
            'stream' => true,
            'auth' => [
                config('svarinn.username'),
                config('svarinn.secret')
            ],
        ];
    }

    /**
     * Returnerer en GuzzleHttp-klient
     */
    protected function getClient() {
        return new GuzzleClient([
            'allow_redirects' => true,
        ]);
    }
    /**
     * Henter ned forsendelsesfilen som er oppgitt i meldingen
     */
    protected function hentForsendelsefil($uri) {
        $fileResponse = $this->getClient()->get($uri, $this->getOptions());

        $contentType = $fileResponse->getHeader('content-type');
        // Henter filnavn fra header content-disposition - 'attachment; filename="dokumenter-7104a48e.zip"'
        $fileName = preg_replace('/.*\"(.*)"/','$1', $fileResponse->getHeader('content-disposition')[0]);
        file_put_contents(config('svarinn.download_path').'/'.$fileName, $fileResponse->getBody()->getContents());
        return $fileName;
    }

    /**
     * Kvitterer for at meldingen er mottatt
     * @param string    $id     Meldingens ID i SvarUt
     */
    protected function kvitterForMottak($id) {
        $uri = config('svarinn.base_uri').config('svarinn.urlSettMottatt').'/'.$id;

        $result = $this->getClient()->post($uri, $this->getOptions());

        if ($result->getStatusCode() == '200') return true;

        return false;
    }

    /**
     * Merker en forsendelse som feilet
     */
    protected function forsendelseFeilet($id, $permanent = false, $melding = null) {
        $uri = config('svarinn.base_uri').config('svarinn.urlMottakFeilet').'/'.$id;

        $melding = $melding == null ? 'En feil oppsto under innhenting.': $melding;
        $body = [
            'feilmelding' => $melding,
            'permanent' => $permanent,
        ];
        $options = $this->getOptions();
        $options['json'] = $body;
        $result = $this->getClient()->post($uri, $options);
        if ($result->getStatusCode() == '200') return true;

        return false;
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
        if (file_exists(config('svarinn.download_path').'/'.$fileName)):
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
            $this->error($this->l3.'Fant ikke fila som skulle dekrypteres ['.config('svarinn.download_path').'/'.$fileName.']');
            $exitCode = 2;
        endif;
        return $exitCode;
    }
}
