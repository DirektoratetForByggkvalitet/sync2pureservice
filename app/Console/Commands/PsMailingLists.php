<?php

namespace App\Console\Commands;

use App\Models\Company;
use Illuminate\Console\Command;
use App\Services\{PsApi, Tools};
use Illuminate\Support\{Arr, Collection, Str};

class PsMailingLists extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'pureservice:mailing-lists';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    protected PsApi $ps;
    protected Collection $lists;
    protected Collection $relationshipTypes;

    /**
     * Execute the console command.
     */
    public function handle() : int {
        $this->ps = new PsApi();
        /**
         * Henter inn alle mottakerlister
         */
        $uri = '/asset/';
        $params = [
            'filter' => 'type.name=="'.config('pureservice.dispatch.assetTypeName').'"',
        ];
        if ($result = $this->ps->apiQuery($uri, $params)):
            $this->lists = collect($result['assets']);
        else:
            $this->error('Fant ingen mottakerliste');
            return Command::FAILURE;
        endif;

        $uri = '/relationshiptype/';
        $params = [
            'filter' => 'fromAssetType.name=="'.config('pureservice.dispatch.assetTypeName').'"',
        ];
        if ($result = $this->ps->apiQuery($uri, $params)):
            $this->relationshipTypes = collect($result['relationshiptypes']);
        else:
            $this->error('Fant ingen relasjonstyper.');
            return Command::FAILURE;
        endif;


        /**
         * Henter alle statlige virksomheter (dep o.l.) fra Pureservice
         */
        $categories = config('pureservice.company.categoryMap');
        foreach ($categories as $code => $category):
            $list = $this->lists->firstWhere('uniqueId', $code);
            $uri = '/company/';
            $params = [
                'filter' => config('pureservice.company.categoryfield').' == "'.$category.'"',
            ];
            $result = $this->ps->apiQuery($uri, $params);
            if ($list && count($result['companies']) > 0):
                $this->line(Tools::l1().'Kobler firma av kategorien \''.$category.'\' til listen \''.$list['name'].'\'');
                $this->relateCompaniesToList($result['companies'], $list);
                $this->newLine();
            endif;
        endforeach;
        $this->newLine();
        $this->info('Fullført');
        return Command::SUCCESS;
    }

    protected function relateCompaniesToList(array $companies, array $list): void {
        $uri = '/relationship/';
        $relationshipType = $this->relationshipTypes->firstWhere('name', config('pureservice.dispatch.listRelationName.toCompany'));
        $listRelUri = $uri . $list['id'].'/fromAsset';
        $params = [
            'filter' => 'toCompanyId != NULL AND typeId == '.$relationshipType['id'],
        ];
        if ($listRelResult = $this->ps->apiQuery($listRelUri, $params)):
            $listRelations = collect($listRelResult['relationships']);
            $this->line(Tools::l2().'Sletter listens relasjoner til alle firma');
            $bar = $this->output->createProgressBar($listRelations->count());
            $bar->start();
            $listRelations->each(function (array $relation, int $key) use ($uri, $bar) {
                $delUri = $uri . $relation['id'] . '';
                $this->ps->apiDelete($delUri);
                $bar->advance();
            });
            $bar->finish();
        endif;
        unset($listRelResult, $listRelations);
        $this->newLine();
        $this->line(Tools::l2().'Kobler firma til listen');
        $bar = $this->output->createProgressBar(count($companies));
        $bar->start();

        foreach ($companies as $c):
            // Finnes ikke fra før av. Oppretter relasjonen
            $body = ['relationships' => []];
            $body['relationships'][] = [
                'main' => 'toAssetId',
                'inverseMain' => 'fromAssetId',
                'links' => [
                    'type' => ['id' => $relationshipType['id']],
                    'toAsset' => ['id' => $list['id']],
                    'fromCompany' => ['id' => $c['id']]
                ],
            ];
            $result = $this->ps->apiPost($uri, $body);
            $bar->advance();
        endforeach;
        $bar->finish();
    }
}
