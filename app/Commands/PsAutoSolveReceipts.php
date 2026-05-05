<?php

namespace App\Commands;

use Illuminate\Console\Scheduling\Schedule;
use LaravelZero\Framework\Commands\Command;
use App\Services\{PsApi, Tools};
use Illuminate\Support\Str;

class PsAutoSolveReceipts extends Command
{
    protected $signature = 'pureservice:auto-løs-kvitteringer
                            {--dry-run : Simuler uten å gjøre endringer i Pureservice}';

    protected $description = 'Auto-kategoriserer åpne saker fra ikke-svar@dfo.no med ett vedlegg (PDF/XLSX/LIS)';

    protected PsApi $ps;

    // Avsenderadresse det skal filtreres på
    protected string $senderEmail = 'ikke-svar@dfo.no';

    // Sakstype som skal settes
    protected string $targetTicketType = 'Interne saksforberedelser';

    // Kategorier i punkt-notasjon: Nivå1.Nivå2.Nivå3
    protected string $targetCategories = 'Økonomi.Faktura.Kvittering';
    protected string $solution         = 'Kvittering: Løst automatisk';

    public function handle(): int {
        $dryRun = $this->option('dry-run');

        $this->info(class_basename($this));
        $this->line($this->description);
        if ($dryRun) $this->warn('-- DRY RUN: ingen endringer vil bli lagret --');
        $this->newLine();

        $this->ps = new PsApi();

        // Slå opp sakstype-ID og kategori-IDer én gang
        $ticketTypeId = $this->ps->getEntityId('tickettype', $this->targetTicketType);
        if (!$ticketTypeId) {
            $this->error('Fant ikke sakstype "' . $this->targetTicketType . '" i Pureservice');
            return Command::FAILURE;
        }

        $categories = $this->ps->getCategoriesFromDotName($this->targetCategories);
        if (!$categories || !array_filter($categories)) {
            $this->error('Fant ikke kategoriene "' . $this->targetCategories . '" i Pureservice');
            return Command::FAILURE;
        }

        $solvedStatusId = $this->ps->getEntityId('status', config('pureservice.ticket.status_solved'));
        if (!$solvedStatusId) {
            $this->error('Fant ikke løst-status i Pureservice');
            return Command::FAILURE;
        }

        // Hent og behandle åpne saker side for side (200 om gangen)
        $this->line(Tools::L1 . 'Behandler åpne saker fra ' . $this->senderEmail . ' (200 om gangen)...');
        $limit = 200;
        $query = [
            'include' => 'attachments',
            'filter' => 'user.emailaddress.email == "' . $this->senderEmail . '"'
                . ' AND ' . config('pureservice.corestatus.open')
                . ' AND ticketType.name != "' . $this->targetTicketType . '"',
            'limit' => $limit,
            'start' => 0,
        ];

        $updated    = 0;
        $skipped    = 0;
        $failed     = 0;
        $batchCount = $limit;
        $patchBody = array_merge(
            ['ticketTypeId' => $ticketTypeId],
            array_filter($categories, fn($v) => $v !== null),
            [
                'statusId' => $solvedStatusId,
                'solution' => $this->solution,
            ]
        );

        while ($batchCount === $limit):
            $batchCount    = 0;
            $notSolved     = 0;
            $response = $this->ps->apiQuery('/ticket/', $query, true);
            if (!$response->successful()) {
                $this->error('API-kall mot Pureservice feilet: ' . $response->status());
                return Command::FAILURE;
            }

            $tickets     = $response->json('tickets', []);
            $batchCount  = count($tickets);

            if ($batchCount === 0) break;

            $attachments = collect($response->json('linked.attachments', []));
            unset($response);

            foreach ($tickets as $ticket) {
                $reqNo = $ticket['requestNumber'];

                // Finn vedlegg for denne saken
                $ticketAttachments = $attachments->filter(
                    fn($att) => $att['ticketId'] === $ticket['id']
                );

                // Kriterium: nøyaktig ett vedlegg
                if ($ticketAttachments->count() !== 1) {
                    $this->line(Tools::L2 . 'Sak #' . $reqNo . ': hoppet over (antall vedlegg: ' . $ticketAttachments->count() . ')');
                    $skipped++;
                    $notSolved++;
                    continue;
                }

                // Kriterium: vedlegget er PDF, XLSX eller LIS
                $attachment = $ticketAttachments->first();
                $ext = Str::lower(Str::afterLast($attachment['fileName'] ?? '', '.'));
                if (!in_array($ext, ['pdf', 'xlsx', 'lis'])) {
                    $this->line(Tools::L2 . 'Sak #' . $reqNo . ': hoppet over (filtype: ' . $ext . ')');
                    $skipped++;
                    $notSolved++;
                    continue;
                }

                // Begge kriterier oppfylt — kategoriser og løs saken
                $this->line(Tools::L2 . 'Sak #' . $reqNo . ': kategoriserer og løser (vedlegg: ' . $attachment['fileName'] . ')');

                if (!$dryRun) {
                    $patchResponse = $this->ps->apiPatch('/ticket/' . $ticket['id'], $patchBody, 'application/json', 'application/vnd.api+json');
                    if ($patchResponse->successful()) {
                        $updated++;
                    } else {
                        $this->error(Tools::L2 . 'Sak #' . $reqNo . ': oppdatering feilet (HTTP ' . $patchResponse->status() . ')');
                        $failed++;
                        $notSolved++;
                    }
                } else {
                    $updated++;
                }
            }

            unset($tickets, $attachments);
            // Løste saker faller ut av filteret — hopp bare over de som ble igjen
            $query['start'] += $notSolved;
        endwhile;

        if ($updated + $skipped + $failed === 0) {
            $this->info('Ingen åpne saker funnet fra ' . $this->senderEmail . '.');
            return Command::SUCCESS;
        }

        $this->newLine();
        $suffix = $dryRun ? ' (dry run)' : '';
        $this->info('Ferdig' . $suffix . '. ' . $updated . ' sak(er) kategorisert og løst, ' . $skipped . ' hoppet over'
            . ($failed ? ', ' . $failed . ' feilet' : '') . '.');

        return $failed ? Command::FAILURE : Command::SUCCESS;
    }

    public function schedule(Schedule $schedule): void {
        // $schedule->command(static::class)->dailyAt('06:00');
    }
}
