<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\{Str, Arr, Collection};
use App\Services\{PsApi, Tools};
use App\Models\User;

/**
 * Brukes til å gå gjennom alle sluttbrukere i Pureservice og oppdatere de som har e-postadresse i fornavn og/eller etternavn
 */
class PsUserCleanup extends Command {

    protected Collection $psUsers;
    protected Collection $linked;
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
        $emailData = collect($emailData);

        $userCount = $this->psUsers->count();
        $this->info(Tools::L1.'Vi fant '.$userCount.' sluttbrukere. Starter behandling...');
        $this->psUsers->each(function (User $psUser, int $key) use ($emailData, $ps){
            $email = $emailData->firstWhere('userId', $psUser->id);
            $psUser->email = $email['email'];
            $this->line(Tools::L2.$key.'. ID '.$psUser->id.' - '.$psUser->firstName. ' '. $psUser->lastName);
            $fullName = $psUser->firstName.' '.$psUser->lastName;
            if (Str::contains($fullName, ['@', '.com']) || $fullName == ' '):
                $newName = Tools::nameFromEmail($psUser->email);
            endif;
            if (Str::contains($fullName, ', ')):
                $newName = Tools::reorderName($fullName);
            endif;
            if (isset($newName)):
                $psUser->firstName = $newName[0];
                $psUser->lastName = $newName[1];
                $this->line(Tools::L3.'Endres til '.implode(' ', $newName));
            endif;
    });

        return Command::SUCCESS;

    }
}
