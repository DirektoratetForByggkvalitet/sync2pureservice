<?php

namespace App\Commands;

use Carbon\Carbon;
use Illuminate\Console\Scheduling\Schedule;
use LaravelZero\Framework\Commands\Command;
use App\Services\{PsApi, Tools};
use Illuminate\Support\Str;

class AutoSolveDigdirTickets extends Command
{
    protected $signature = 'pureservice:auto-solve-digdir-tickets
                            {--dry-run : Simuler uten å gjøre endringer i Pureservice}';

    protected $description = 'Løser automatisk åpne Digdir-varsler eldre enn 60 dager fra noreply@varsel.digdir.no';

    protected PsApi $ps;

    protected string $senderEmail  = 'noreply@varsel.digdir.no';
    protected int    $ageDays      = 60;
    protected string $baseCategory = 'Fellestjeneste';

    // Tjenester som kan gi kategori 2 om de nevnes i emnefeltet
    protected array $serviceKeywords = [
        'Altinn', 'eFormidling', 'ELMA', 'ID-porten', 'Maskinporten', 'Ansattporten',
    ];

    public function handle(): int {
        $dryRun = $this->option('dry-run');

        $this->info(class_basename($this));
        $this->line($this->description);
        if ($dryRun) $this->warn('-- DRY RUN: ingen endringer vil bli lagret --');
        $this->newLine();

        $this->ps = new PsApi();

        // Slå opp løst-status
        $solvedStatusId = $this->ps->getEntityId('status', config('pureservice.ticket.status_solved'));
        if (!$solvedStatusId) {
            $this->error('Fant ikke løst-status i Pureservice');
            return Command::FAILURE;
        }

        // Pre-bygg kategori-kart for alle mulige kombinasjoner
        $this->line(Tools::L1 . 'Slår opp kategorier i Pureservice...');
        $categoryMap = $this->buildCategoryMap();
        if (empty($categoryMap)) {
            $this->error('Fant ikke basiskategorien "' . $this->baseCategory . '" i Pureservice');
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

        $updated    = 0;
        $failed     = 0;
        $batchCount = $limit;

        while ($batchCount === $limit):
            $batchCount = 0;
            $notSolved  = 0;
            $response   = $this->ps->apiQuery('/ticket/', $query, true);

            if (!$response->successful()) {
                $this->error('API-kall mot Pureservice feilet: ' . $response->status());
                return Command::FAILURE;
            }

            $tickets    = $response->json('tickets', []);
            $batchCount = count($tickets);
            unset($response);

            foreach ($tickets as $ticket) {
                $reqNo   = $ticket['requestNumber'];
                $subject = $ticket['subject'] ?? '';

                $categories = $this->resolveCategories($subject, $categoryMap);
                $solution   = $this->resolveSolution($subject);
                $service    = $this->resolveServiceKeyword($subject);

                $this->line(Tools::L2 . 'Sak #' . $reqNo . ': '
                    . ($service ? '"' . $service . '" — ' : '')
                    . '"' . Str::limit($subject, 60) . '"');
                $this->line(Tools::L2 . '  Løsning: ' . $solution);

                if (!$dryRun) {
                    $patchBody = array_merge(
                        array_filter($categories, fn($v) => $v !== null),
                        [
                            'statusId' => $solvedStatusId,
                            'solution' => $solution,
                        ]
                    );

                    $patchResponse = $this->ps->apiPatch(
                        '/ticket/' . $ticket['id'],
                        $patchBody,
                        'application/json',
                        'application/vnd.api+json'
                    );

                    if ($patchResponse->successful()) {
                        $updated++;
                    } else {
                        $this->error(Tools::L2 . '  Feilet (HTTP ' . $patchResponse->status() . ')');
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

    /**
     * Pre-henter kategori-IDer for alle mulige kombinasjoner og returnerer et kart.
     * Nøkkel '' er fallback (kun Fellestjeneste), øvrige nøkler er tjenestenavnet.
     */
    protected function buildCategoryMap(): array {
        $map  = [];

        // Basiskategori (kun nivå 1)
        $base = $this->ps->getCategoriesFromDotName($this->baseCategory);
        if (!$base || !array_filter($base)) return [];
        $map[''] = $base;

        // Tjenestespesifikke underkategorier (nivå 2)
        foreach ($this->serviceKeywords as $keyword) {
            $cats = $this->ps->getCategoriesFromDotName($this->baseCategory . '.' . $keyword);
            if ($cats && !empty(array_filter($cats))) {
                $map[$keyword] = $cats;
                $this->line(Tools::L2 . 'Kategori funnet: ' . $this->baseCategory . '.' . $keyword);
            } else {
                $this->line(Tools::L2 . 'Ingen underkategori for "' . $keyword . '" — bruker basiskategori');
            }
        }

        return $map;
    }

    /**
     * Returnerer det første tjenestenavnet som finnes i emnefeltet, eller null.
     */
    protected function resolveServiceKeyword(string $subject): ?string {
        foreach ($this->serviceKeywords as $keyword) {
            if (Str::contains($subject, $keyword, true)) {
                return $keyword;
            }
        }
        return null;
    }

    /**
     * Returnerer kategori-IDer basert på emnefeltet.
     */
    protected function resolveCategories(string $subject, array $categoryMap): array {
        $keyword = $this->resolveServiceKeyword($subject);
        if ($keyword && isset($categoryMap[$keyword])) {
            return $categoryMap[$keyword];
        }
        return $categoryMap[''];
    }

    /**
     * Velger løsningstekst basert på emnefeltet.
     */
    protected function resolveSolution(string $subject): string {
        if (Str::contains($subject, 'maintenance', true)) {
            return 'Vedlikeholdet har blitt utført.';
        }
        if (Str::contains($subject, 'incident', true)) {
            return 'Problemet ser ut til å være løst.';
        }
        return 'Saken har blitt løst pga tidsavbrudd.';
    }

    public function schedule(Schedule $schedule): void {
        // $schedule->command(static::class)->dailyAt('07:00');
    }
}
