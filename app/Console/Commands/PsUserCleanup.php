<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\{Str, Arr, Collection};
use Illuminate\Support\Facades\Cache;
use App\Services\{PsApi, Tools};
use App\Models\User;

/**
 * Brukes til å gå gjennom alle sluttbrukere i Pureservice og oppdatere de som har e-postadresse i fornavn og/eller etternavn
 */
class PsUserCleanup extends Command {

    protected Collection $psUsers;
    protected Collection $emailAddresses;
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
        $ps = new PsApi();
        $userCount = 0;
        if (Cache::has('psUsers') && Cache::has('emailAddresses')):
            $this->info(Tools::L1.'Henter brukerdata fra cache');
            $this->psUsers = Cache::get('psUsers');
            $this->emailAddresses = Cache::get('emailAddresses');
        else:
            $this->info(Tools::L1.'Henter brukerdata fra '.$ps->base_url);
            $uri = '/user/';
            $AND = ' && ';
            $query = [
                'filter' => 'role == '.config('pureservice.user.role_id') . $AND . '!disabled',
                'include' => 'emailaddress',
                'limit' => 500,
                'start' => 0,
                'sort' => 'lastName ASC, firstName ASC',
            ];
            $batchCount = 500;
            $psUsers = [];
            $emailData = [];
            while ($batchCount == 500):
                $response = $ps->apiQuery($uri, $query, true);
                if ($response->successful()):
                    $psUsers = array_merge($psUsers, $response->json('users'));
                    $emailData = array_merge($emailData, $response->json('linked.emailaddresses'));
                else:
                    continue;
                endif;
                $query['start'] = $query['start'] + $query['limit'];
                $batchCount = count($response->json('users'));
            endwhile;
            $this->psUsers = collect($psUsers)->mapInto(User::class);
            $this->emailAddresses = collect($emailData);
            unset($response, $psUsers, $emailData);
            // Legger brukerdataene i cache inntil videre
            Cache::add('psUsers', $this->psUsers, 3600);
            Cache::add('emailAddresses', $this->emailAddresses, 3600);
        endif;

        $userCount = $this->psUsers->count();
        $changeCount = 0;
        $this->info(Tools::L1.'Vi fant '.$userCount.' sluttbrukere. Starter behandling...');
        $this->psUsers->lazy()->each(function (User $psUser, int $key) use ($ps, &$changeCount) {
            $updateMe = false;
            $companyChanged = false;
            $email = $this->emailAddresses->firstWhere('userId', $psUser->id);
            $psUser->email = $email['email'];
            $fullName = $psUser->firstName.' '.$psUser->lastName;
            if (Str::contains($fullName, ['@', '.com', '.biz', '.net', '.ru']) || $fullName == ' '):
                $newName = Tools::nameFromEmail($psUser->email);
                $updateMe = true;
            endif;
            if (Str::contains($fullName, ', ') && !Str::startsWith($fullName, ['SvarUt', 'eFormidling', 'Postmottak', 'Post'])):
                $newName = Tools::reorderName($fullName);
                $updateMe = true;
            endif;

            // Ser om det finnes et firma med samme domenenavn og kobler i tilfelle det opp mot brukeren
            $emailDomain = Str::after($psUser->email, '@');
            if (Str::afterLast($emailDomain, '.') == 'no' && !in_array($emailDomain, ['epost.no', 'online.no', 'altibox.no']) && $company = $ps->findCompanyByDomainName('@'.$emailDomain, true)):
                if ($company->id != $psUser->companyId):
                    $psUser->companyId = $company->id;
                    $updateMe = true;
                    $companyChanged = true;
                endif;
            endif;
            if ($updateMe):
                $changeCount++;
                $this->info(Tools::L2.$key.'. \''.$fullName.'\' - '.$psUser->email.' [ID '.$psUser->id.']');
                if (isset($newName)):
                    $psUser->firstName = Str::title($newName[0]);
                    $psUser->lastName = Str::title($newName[1]);
                    $this->line(Tools::L3.'- Navn: '.$psUser->firstName.' '.$psUser->lastName);
                endif;
                if ($companyChanged):
                    $this->line(Tools::L3.'- Kobles til virksomheten \''.$company->name.'\'');
                endif;
                // Oppdater brukeren i Pureservice
                $this->newLine();
            endif;
        });

        $this->info('Ferdig. Av til sammen '.$userCount.' brukere måtte '.$changeCount.' endres på.');

        return Command::SUCCESS;

    }
}
