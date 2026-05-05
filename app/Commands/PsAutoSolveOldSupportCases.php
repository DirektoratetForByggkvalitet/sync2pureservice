<?php

namespace App\Commands;

use Carbon\Carbon;
use Illuminate\Console\Scheduling\Schedule;
use LaravelZero\Framework\Commands\Command;
use App\Services\{PsApi, Tools};

class PsAutoSolveOldSupportCases extends Command
{
    protected $signature = 'pureservice:auto-løs-supportsaker
                            {--dry-run : Simuler uten å gjøre endringer i Pureservice}';

    protected $description = 'Løser automatisk åpne regnskapssaker eldre enn 60 dager fra regnskap@dfo.no';

    protected PsApi $ps;

    protected string $senderEmail    = 'regnskap@dfo.no';
    protected string $targetType     = 'Internsak';
    protected string $targetCategory = 'Økonomi';
    protected string $solution       = 'Support-melding: Løst automatisk';
    protected int    $ageDays        = 60;

    public function handle(): int {
        $dryRun = $this->option('dry-run');

        $this->info(class_basename($this));
        $this->line($this->description);
        if ($dryRun) $this->warn('-- DRY RUN: ingen endringer vil bli lagret --');
        $this->newLine();

        $this->ps = new PsApi();

        // Slå opp IDs én gang før løkken
        $ticketTypeId = $this->ps->getEntityId('tickettype', $this->targetType);
        if (!$ticketTypeId) {
            $this->error('Fant ikke sakstype "' . $this->targetType . '" i Pureservice');
            return Command::FAILURE;
        }

        $categories = $this->ps->getCategoriesFromDotName($this->targetCategory);
        if (!$categories || !array_filter($categories)) {
            $this->error('Fant ikke kategori "' . $this->targetCategory . '" i Pureservice');
            return Command::FAILURE;
        }

        $solvedStatusId = $this->ps->getEntityId('status', config('pureservice.ticket.status_solved'));
        if (!$solvedStatusId) {
            $this->error('Fant ikke løst-status i Pureservice');
            return Command::FAILURE;
        }

        $cutoff = Carbon::now(config('app.timezone'))->subDays($this->ageDays);
        $cutoffFilter = sprintf(
            'DateTimeOffset(%d, %d, %d, 0, 0, 0, 000, TimeSpan(0, %d, 0, 0))',
            $cutoff->year,
            $cutoff->month,
            $cutoff->day,
            $cutoff->offsetHours
        );

        // Hent og behandle saker side for side (200 om gangen)
        $this->line(Tools::L1 . 'Behandler åpne saker fra ' . $this->senderEmail
            . ' eldre enn ' . $this->ageDays . ' dager...');

        $limit = 200;
        $query = [
            'filter' => 'user.emailaddress.email == "' . $this->senderEmail . '"'
                . ' AND ' . config('pureservice.corestatus.open')
                . ' AND created < ' . $cutoffFilter,
            'limit'  => $limit,
            'start'  => 0,
        ];

        $patchBody = array_merge(
            ['ticketTypeId' => $ticketTypeId],
            array_filter($categories, fn($v) => $v !== null),
            [
                'visibility' => config('pureservice.visibility.invisible'),
                'statusId'   => $solvedStatusId,
                'solution'   => $this->solution,
            ]
        );

        $updated    = 0;
        $failed     = 0;
        $batchCount = $limit;

        while ($batchCount === $limit):
            $batchCount = 0;
            $notSolved  = 0;
            $response = $this->ps->apiQuery('/ticket/', $query, true);

            if (!$response->successful()) {
                $this->error('API-kall mot Pureservice feilet: ' . $response->status());
                return Command::FAILURE;
            }

            $tickets    = $response->json('tickets', []);
            $batchCount = count($tickets);
            unset($response);

            foreach ($tickets as $ticket) {
                $reqNo = $ticket['requestNumber'];
                $this->line(Tools::L2 . 'Sak #' . $reqNo . ': løser og kategoriserer');

                if (!$dryRun) {
                    $patchResponse = $this->ps->apiPatch(
                        '/ticket/' . $ticket['id'],
                        $patchBody,
                        'application/json',
                        'application/vnd.api+json'
                    );
                    if ($patchResponse->successful()) {
                        $updated++;
                    } else {
                        $this->error(Tools::L2 . 'Sak #' . $reqNo . ': feilet (HTTP ' . $patchResponse->status() . ')');
                        $failed++;
                        $notSolved++;
                    }
                } else {
                    $updated++;
                }
            }

            unset($tickets);
            // Løste saker faller ut av filteret — hopp bare over de som feilet
            $query['start'] += $notSolved;
        endwhile;

        $this->newLine();
        if ($updated + $failed === 0) {
            $this->info('Ingen aktuelle saker funnet.');
            return Command::SUCCESS;
        }

        $suffix = $dryRun ? ' (dry run)' : '';
        $this->info('Ferdig' . $suffix . '. ' . $updated . ' sak(er) løst'
            . ($failed ? ', ' . $failed . ' feilet' : '') . '.');

        return $failed ? Command::FAILURE : Command::SUCCESS;
    }

    public function schedule(Schedule $schedule): void {
        // $schedule->command(static::class)->dailyAt('07:00');
    }
}
