<?php

namespace App\Commands;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Str;
use LaravelZero\Framework\Commands\Command;
use App\Models\{AppSecret, Ticket};
use App\Services\{PsApi};
use Microsoft\Graph\{GraphRequestAdapter, GraphServiceClient};
use Microsoft\Graph\Core\Tasks\PageIterator;
use Microsoft\Graph\Generated\Models\{Application, KeyCredential};
use Microsoft\Graph\Generated\Applications\ApplicationsRequestBuilderGetRequestConfiguration;
use Microsoft\Kiota\Abstractions\ApiException;
//use Microsoft\Kiota\Authentication\PhpLeagueAuthenticationProvider;
use Microsoft\Kiota\Authentication\Oauth\ClientCredentialCertificateContext;

class CheckAppSecrets extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'graph:check-app-secrets';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Holder oversikt over App Registrations i Entra ID';

    protected GraphServiceClient $graph;

    /**
     * Execute the console command.
     */
    public function handle() {
        $this->getGraphClient();
        $this->retrieveAppSecretInfo();
        $this->newLine();
        $this->info('Vi fant '.AppSecret::count(). ' passord og sertifikater knyttet til App Registrations.');
        $notification_date = now('UTC')->addDays(config('appsecret.notify.days'));
        $secrets_to_notify = AppSecret::getExpires();
        if ($secrets_to_notify->count()):
            $this->line('> Av disse er '. $secrets_to_notify->count(). ' i ferd med å utløpe innen '.config('appsecret.notify.days').' dager');
        else:
            $this->line('> Ingen av disse utløper innen '.config('appsecret.notify.days').' dager');
            return Command::SUCCESS;
        endif;

        // Fortsetter å behandle (hvis ikke stoppet ovenfor)
        $this->newLine();
        $this->info('Oppretter sak(er) i Pureservice');
        $this->notifyWithPureservice($secrets_to_notify);
    }

    /**
     * Define the command's schedule.
     */
    public function schedule(Schedule $schedule): void {
        // $schedule->command(static::class)->everyMinute();
    }

    protected function retrieveAppSecretInfo() {
        try {
            $applications = $this->graph->applications()->get()->wait();
            $pageIterator = new PageIterator($applications, $this->graph->getRequestAdapter());
            $count = 0;
            while ($pageIterator->hasNext()):
                $pageIterator->iterate(function (Application $app) use (&$count) {
                    $count++;
                    $keyCreds = array_merge($app->getKeyCredentials(), $app->getPasswordCredentials());
                    foreach ($keyCreds as $keyCred):
                        $new = AppSecret::updateOrCreate(
                            [
                                'id' => $keyCred->getKeyId()
                            ],
                            [
                                'appId' => $app->getId(),
                                'appName' => $app->getDisplayName(),
                                'id' => $keyCred->getKeyId(),
                                'keyType' => 'password',
                                'displayName' => $keyCred->getDisplayName(),
                                'startDateTime' => $keyCred->getStartDateTime(),
                                'endDateTime' => $keyCred->getEndDateTime(),
                            ]
                        );
                        if ($keyCred instanceof KeyCredential):
                            $new->keyType = $keyCred->getType();
                            $new->save();
                        endif; 
                    endforeach;

                });
            endwhile;
        } catch (ApiException $ex) {
            $this->error($ex->getMessage());
            return Command::FAILURE;
        }
    }

    protected function getGraphClient(): bool {
        $config = config('appsecret.credentials');
        $tokenRequestContext = new ClientCredentialCertificateContext(
            $config['tenantId'], 
            $config['clientId'],
            $config['certificatePath'],
            $config['privateKeyPath']
        );
        if ($this->graph = new GraphServiceClient($tokenRequestContext, config('appsecret.scopes'))) return true;
        return false;
    }

    protected function notifyWithPureservice(Collection $secrets): bool {
        $ps = new PsApi();
        $ps->setTicketOptions('app_notification');
        $existingTicket = null;
        $count = 0;
        $secrets->each(function(AppSecret $secret, int $key) use ($ps, &$count) {
            $count++;
            $reference = $secret->getCustomerReference();
            if ($existingTicket = Ticket::firstWhere('customerReference', $reference)):
                // Vi har allerede saken i databasen
            else:
                $existingResponse = $ps->apiQuery('ticket', [
                    'filter' => config('appsecret.notify.refField').'=="'. $reference .'"'.
                        ' AND '.config('pureservice.corestatus.open'),
                ], true);
                if ($existingResponse->successful()):
                    // Det finnes allerede en åpen sak på dette
                    $existingTicket = collect($existingResponse->json('tickets'))->mapInto(Ticket::class)->first();
                endif;
                unset($existingResponse);
            endif;

            $message_template = $existingTicket ? 'note' : 'ticket';
            $subject = Str::replaceArray('?', [$secret->displayType(), $secret->appName], config('appsecret.notify.subject'), );
            $message = Blade::render(config('appsecret.template.'.$message_template), [
                'secret' => $secret
            ]);
            if ($existingTicket):
                $this->line('> Legger internt notat til i eksisterende sak...');
                $ps->createInternalNote($message, $existingTicket['id'], $subject);
            else:
                $this->line('> Oppretter sak i Pureservice...');
                $user = $ps->getCompanyOrUser(config('appsecret.notify.from'));
                $newTicket = $ps->createTicket($subject, $message, $user->id);
                $categories = $ps->getCategoriesFromDotName(config('appsecret.notify.categories'));
                foreach ($categories as $field => $id):
                    if ($id):
                        $newTicket->$field = $id;
                    endif;
                endforeach;
                $newTicket->customerReference = $reference;
                $newTicket->addOrUpdatePS($ps);
            endif;
        });
        $this->newLine();
        $this->info('Ferdig. Opprettet varsel for '.$count.' passord og sertifikater');
        return true;
    }
}
