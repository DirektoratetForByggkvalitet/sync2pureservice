<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\{Pureservice, SvarInn, ExcelLookup, Tools};
use Carbon\Carbon;
use GuzzleHttp\Client as GuzzleClient;
use ZanySoft\Zip\Facades\Zip;
use Illuminate\Support\{Arr, Str};
use Illuminate\Support\Facades\{Storage, Http};

class SvarInn2Pureservice extends Command {
    protected $l1 = '';
    protected $l2 = '> ';
    protected $l3 = '  ';
    protected $start;
    protected Pureservice $ps;
    protected SvarInn $svarInn;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'pureservice:svarUtMottak';

    protected $version = '2.0';

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
    public function handle()
    {
        $this->start = microtime(true);
        $this->info(class_basename($this).' v'.$this->version);
        $this->line($this->description);

        $this->info(Tools::ts().'Setter opp miljøet...');
        $this->setConfig();
        if ($this->checkList() == false) return Command::INVALID;
        $this->line('');

        $this->info(Tools::ts().'Kobler til Pureservice');
        $this->ps = new Pureservice();
        $this->ps->setTicketOptions();

        $this->info(Tools::ts().'Kobler til SvarUt Mottakstjeneste');
        $this->svarInn = new SvarInn();

        if (is_bool(config('svarinn.dryrun'))):
            $this->info(Tools::L2.'Ser etter nye meldinger i SvarInn');
            $msgs = $this->svarInn->sjekkForMeldinger();
        else:
            $this->info(Tools::L2.'Laster inn eksempelmeldinger fra JSON');
            $msgs = json_decode(file_get_contents(storage_path(config('svarinn.dryrun'))), true);
        endif;

        $msgCount = count($msgs);
        $i = 0;
        if ($msgCount > 0):
            $messageString = 'melding';
            if ($msgCount > 1) $messageString = Str::plural($messageString);
            $this->line($this->l3.'Fant '.$msgCount.' '.$messageString);
            foreach ($msgs as $message):
                $i++;

                $dlPath = config('svarinn.path.download').'/'.$message['id'];
                if (Storage::directoryMissing($dlPath)) Storage::makeDirectory($dlPath);
                $tmpPath = config('svarinn.path.tmp').'/'.$message['id'];
                if (Storage::directoryMissing($tmpPath)) Storage::makeDirectory($tmpPath);
                $dekryptPath = config('svarinn.path.dekrypt').'/'.$message['id'];
                if (Storage::directoryMissing($dekryptPath)) Storage::makeDirectory($dekryptPath);

                $this->info(Tools::L2.$i.'/'.$msgCount.': '.$message['tittel']);
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
                    $email = Arr::get($message,'svarSendesTil.orgnr') != null ? Arr::get($message,'svarSendesTil.orgnr') : Str::random(16);
                    $email .= '@'.config('pureservice.user.dummydomain');
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

                $response = Http::withBasicAuth(config('svarinn.api.user'), config('svarinn.api.password'))
                    ->timeout(600)
                    ->connectTimeout(5)
                    ->retry(3, 1000)
                    ->get($message['downloadUrl']);
                $fileMimeType = Str::before($response->header('content-type'), ';');
                $file = trim(Str::after($response->header('content-disposition'), "="), '\'" ');
                $fileName = $dlPath.'/'.$file;
                Storage::put(
                    $fileName,
                    $response->toPsrResponse()->getBody()->getContents()
                );
                unset($response);
                $this->line(Tools::L3.'Lastet ned forsendelsesfil av typen '.$fileMimeType.' på '. Tools::human_filesize(Storage::fileSize($fileName)).'b');
                //$fileName = $this->hentForsendelsefil($message['downloadUrl']);
                $this->line($this->l3.'Dekrypterer forsendelsesfila');
                $decrypted = $this->decryptFile($fileName, $dekryptPath);
                if (!$decrypted):
                    $this->error('Fila ble ikke dekryptert.');
                    return Command::FAILURE;
                endif;
                $fileEnding = Str::lower(Str::afterLast($decrypted, '.'));
                $filesToInclude = [];
                if ( $fileEnding == 'zip'):
                    // Må pakke ut zip-fil til enkeltfiler
                    if (Storage::exists($decrypted)):
                        $this->line(Tools::L2.'Pakker ut zip-filen \''.$decrypted.'\'');
                        $zip = Zip::open(Storage::path($decrypted));

                    elseif (file_exists(app_path(basename($decrypted)))):
                        // Tar høyde for at dekrypter pakker ut til app-rot
                        $zip = Zip::open(app_path(basename($decrypted)));
                    endif;

                    $zip->extract(Storage::path($tmpPath));
                    $zip->close();
                    foreach (new \DirectoryIterator(Storage::path($tmpPath)) as $fileInfo):
                        if($fileInfo->isDot()) continue;
                        $filesToInclude[] = $tmpPath.'/'.$fileInfo->getFilename();
                    endforeach;
                else:
                    $filesToInclude[] = $dekryptPath.'/'.$fileName;
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
                        if ($this->svarInn->settForsendelseMottatt($message['id'])):
                            $this->line($this->l3.'Forsendelsen \''.$message['id'].'\' er kvittert mottatt hos KS');
                        else:
                            $this->error($this->l3.'Forsendelsen \''.$message['id'].'\' kunne ikke settes som mottatt');
                        endif;
                    endif;

                else:
                    $this->error($this->l3.'Feil under oppretting av sak i Pureservice');
                    if (config('svarinn.dryrun') == false) $this->svarInn->settForsendelseFeilet($message['id']);
                endif;
                $this->newLine(2);
            endforeach;
        else:
            $this->line(Tools::L2.'Ingen meldinger å hente');
        endif;

        $this->info('Fullført på '. round(microtime(true) - $this->start, 2).' sekunder');
        return Command::SUCCESS;
    }

    /**
     * Sjekkliste før kjøring av kommandoen
     *
     * @return bool True dersom alt er i orden, false hvis vi ikke kan kjøre.
     */
    protected function checkList(): bool {
        $rv = true;
        $this->info(Tools::ts().'Sjekker oppsettet…');
        if (config('svarinn.api.user') == null):
            $this->error('Brukernavn for SvarUt Mottakservice er ikke satt');
            $rv = false;
        endif;
        if (config('svarinn.api.password') == null):
            $this->error('Passord for SvarUt Mottakservice er ikke satt');
            $rv = false;
        endif;
        if (!Storage::exists(config('svarinn.privatekey_path'))):
            $this->error('Privatnøkkelen for dekryptering er ikke tilgjengelig. Kan ikke lese \''.config('svarinn.privatekey_path').'\'');
            $rv = false;
        endif;
        if (is_string(config('svarinn.dryrun')) && !is_readable(storage_path(config('svarinn.dryrun')))):
            $this->error('JSON-fil for innlesing av forsendelser er ikke tilgjengelig. Kan ikke lese \''.storage_path(config('svarinn.dryrun')).'\'');
            $rv = false;
        endif;
        if (config('pureservice.api_url') == null):
            $this->error('URL for Pureservice mangler');
            $rv = false;
        endif;
        if (config('pureservice.apikey') == null):
            $this->error('API-nøkkel for Pureservice mangler');
            $rv = false;
        endif;
        return $rv;
    }

    /**
     * Utvider config til å inkludere svarut-dekrypter sitt oppsett
     */
    protected function setConfig(): void {
        if (config('svarinn.dekrypter.jar') == null):
            config([
                'svarinn.dekrypter.jar' => base_path('dekrypter-'.config('svarinn.dekrypter.version').'/dekrypter-'.config('svarinn.dekrypter.version').'.jar')
            ]);
        endif;

        // Sikrer at mapper finnes
        //is_dir(config('svarinn.temp_path')) ? true : mkdir(config('svarinn.temp_path'), 0770, true);
        //is_dir(config('svarinn.dekrypt_path')) ? true : mkdir(config('svarinn.dekrypt_path'), 0770, true);
        //is_dir(config('svarinn.download_path')) ? true : mkdir(config('svarinn.download_path'), 0770, true);
    }


    /**
     * Dekrypterer fil fra SvarUt
     * Vi må bruke et java-bibliotek for å dekryptere filer fra SvarUt,
     * Bouncycastle gjør ting på litt andre måter enn det PHP er i stand til
     *
     * @param  string   $fileName    Filnavnet til den krypterte fila
     * @return int      $returnValue Verdi som angir resultatkoden fra dekrypteringen
     */
    protected function decryptFile(string $fileName, string $destPath): string|false {
        if (Storage::exists($fileName)):
            $dekrypt = system (
                'java -jar ' . config('svarinn.dekrypter.jar').' -k ' . Storage::path(config('svarinn.privatekey_path')).
                ' -s "' . Storage::path($fileName).'"' .
                ' -t "'. Storage::path($destPath).'"',
                $exitCode
            );
            if ($exitCode != 0):
                $this->error($this->l3.'Fila ble ikke dekryptert');
                $this->line($this->l3.$dekrypt);
            endif;
        else:
            $this->error($this->l3.'Fant ikke fila som skulle dekrypteres ['.$fileName.']');
            $exitCode = 2;
            return false;
        endif;
        return $destPath.'/'.Str::afterLast($fileName, '/');
    }
}
