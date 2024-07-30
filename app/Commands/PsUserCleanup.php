<?php

namespace App\Commands;

use Illuminate\Console\Scheduling\Schedule;
use LaravelZero\Framework\Commands\Command;
use Illuminate\Support\{Str, Arr, Collection};
use Illuminate\Support\Facades\Cache;
use App\Services\{PsApi, Tools};
use App\Models\User;

/**
 * Brukes til å gå gjennom alle sluttbrukere i Pureservice og oppdatere de som har e-postadresse i fornavn og/eller etternavn
 */
class PsUserCleanup extends Command {
    protected float $start;
    protected string $version = '1.5';
    protected int $changeCount = 0;
    protected PsApi $ps;
    protected bool $debug = false;
    protected array $report = [];
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'pureservice:user-cleanup';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Går gjennom alle sluttbrukere i Pureservice og sjekker brukernavn for e-postadresser';

    /**
     * Execute the console command.
     */
    public function handle() {
        $this->start = microtime(true);
        $this->info(class_basename($this).' v'.$this->version);
        $this->line($this->description);
        $this->newLine(2);

        $this->debug = true; // config('app.debug');
        $this->report = [
            'Antall brukere' => 0,
            'Navn endret' => 0,
            'Koblet til firma' => 0,
        ];
        $this->ps = new PsApi();
        $userCount = 0;

        $this->info(Tools::L1.'Henter brukerdata fra \''.$this->ps->base_url.'\'');
        $uri = '/user/';
        $AND = ' && ';
        $query = [
            'filter' => 'role == '.config('pureservice.user.role_id') . $AND . '!disabled',
            'include' => 'emailaddress',
            'limit' => 250,
            'start' => 0,
            'sort' => 'lastName ASC, firstName ASC',
        ];
        $batchCount = 250;
        $rTotal = 0;
        while ($batchCount == 250):
            $response = $this->ps->apiQuery($uri, $query, true);
            if ($response->successful()):
                $batchCount = count($response->json('users'));
                $emails = collect($response->json('linked.emailaddresses'));
                collect($response->json('users'))
                    ->mapInto(User::class)
                    ->each(function (User $user) use ($emails) {
                    if ($user != User::firstWhere('id', $user->id)):
                        $email = $emails->firstWhere('userId', $user->id);
                        $user->email = $email['email'];
                        $user->save();
                    endif;
                });
                $rTotal += $batchCount;
                $this->info(Tools::L2.$rTotal.' hentet');
            else:
                continue;
            endif;
            $query['start'] = $query['start'] + $query['limit'];
        endwhile;
        unset($response, $batchCount);

        $userCount = User::all('id')->count();
        $this->report['Antall brukere'] = $userCount;
        $this->newLine();
        $this->changeCount = 0;
        $this->info(Tools::L1.'Vi fant '.$userCount.' sluttbrukere. Starter behandling...');
        $this->newLine();
        User::lazy()->each(function (User $psUser, int $key) {
            $fullName = $psUser->firstName.' '.$psUser->lastName;
            $this->info(Tools::L2.'ID '.$psUser->id.' \''.$fullName.'\': '.$psUser->email);
            $updateMe = false;
            $deleteMe = false;
            $companyChanged = false;
            $deleteMe = in_array(Str::after($psUser->email, '@',), config('pureservice.domain_disable'));
            // $email = $this->emailAddresses->firstWhere('userId', $psUser->id);
            // $psUser->email = $email['email'];
            if (Str::contains($fullName, ['@', '.com', '.biz', '.net', '.ru']) || $psUser->firstName == '' || $psUser->lastName == ''):
                $newName = Tools::nameFromEmail($psUser->email);
                $updateMe = true;
            endif;
            if (Str::contains($fullName, ', ') && !Str::startsWith($fullName, ['SvarUt', 'eFormidling', 'Postmottak', 'Post'])):
                $newName = Tools::reorderName($fullName);
                $updateMe = true;
            endif;

            // Ser om det finnes et firma med samme domenenavn og kobler i tilfelle det opp mot brukeren
            $emailDomain = Str::after($psUser->email, '@');
            if ($company = $this->ps->findCompanyByDomainName($emailDomain, true)):
                if ($company->id != $psUser->companyId):
                    $psUser->companyId = $company->id;
                    $updateMe = true;
                    $companyChanged = true;
                endif;
            endif;
            if ($updateMe && !$deleteMe):
                $this->changeCount++;
                //if ($this->debug) $this->info(Tools::L2.'ID '.$psUser->id.' \''.$fullName.'\': '.$psUser->email);
                if (isset($newName)):
                    $psUser->firstName = Str::title($newName[0]);
                    $psUser->lastName = Str::title($newName[1]);
                    $this->line(Tools::L3.' Navn endres til \''.$psUser->firstName.' '.$psUser->lastName.'\'');
                    $this->report['Navn endret']++;
                endif;
                if ($companyChanged):
                    $this->line(Tools::L3.' Kobles til virksomheten \''.$company->name.'\'');
                    $this->report['Koblet til firma']++;
                endif;
                // Oppdater brukeren i Pureservice
                $psUser->addOrUpdatePS($this->ps);
            endif;
            if ($deleteMe):
                // Brukeren har en e-postadresse som ikke er i bruk lenger
                $this->line(Tools::L3.' Brukeren deaktiveres: E-postadressen er i et domene som ikke er i bruk lenger.');
                $this->ps->disableCompanyOrUser($psUser);
            endif;
            $this->newLine();
        });

        $this->info('####');
        $this->line('Sluttrapport');
        $this->table(array_keys($this->report), [array_values($this->report)]);
        $this->line(Tools::L1.'Vi brukte '.round((microtime(true) - $this->start), 2).' sekunder på dette');
        $this->info('####');
        return Command::SUCCESS;
    }
}
