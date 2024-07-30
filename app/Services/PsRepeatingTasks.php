<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\{Collection, Str};

class PsRepeatingTasks extends PsApi {
    protected Collection $assetType;
    protected Collection $fields;
    protected Collection $fieldItems;


    public function __construct() {
        parent::__construct();
        $this->init();
    }

    protected function init(): void {
        // Henter assetType fra Pureservice
        $uri = '/assettype/';
        $query = [
            'filter' => 'name == '.Str::wrap(config('pureservice.repeating.resourceName'), '"'),
            'include' => 'fields,fields.items',
        ];

        $result = $this->apiQuery($uri, $query, true);
        if ($result->successful()):
            $this->assetType = collect($result->json('assettypes'));
            $this->fields = collect($result->json('linked.assettypefields'));
            $this->fieldItems = collect($result->json('linked.assettypeitems'));
        endif;
    }

    public function getAssetFieldName(string $fName): string {
        return $this->fields->where('name', $fName)->value('propertyName');
    }

    public function getAssetFieldDefinition(string $fName): Collection {
        return $this->fields->where('name', $fName);
    }

    /**
     * Returnerer verdien til et felt
     */
    public function getAssetFieldValue(string $fName, Collection $asset) {
        $fieldDefinition = $this->getAssetFieldDefinition($fName);
        $fieldValue = $asset->value($fieldDefinition->value('propertyName'));
        $fieldType = $fieldDefinition->value('type');
        if (Str::contains($fieldType, ['combobox'])):
            return $this->fieldItems->where('id', $fieldValue);
        elseif (Str::contains($fieldType, ['datepicker'])):
            return Carbon::parse($fieldValue);
        else: // Str::contains($fieldType, ['textarea', 'textfield', 'user'])
            return $fieldValue;
        endif;
    }

    public function getTasks(Carbon|null $date = null): Collection|null {
        $date = $date ? $date : Carbon::today();
        $uri = '/asset/';
        $query = [
            'filter' => 'type.name == '.Str::wrap(config('pureservice.repeating.resourceName'), '"'),
        ];
        $response = $this->apiQuery($uri, $query, true);
        if ($response->successful()):
            $tasks = collect($response->json('assets'));
            return $tasks->filter(function (array $task, int $key) {
                dd($task);
                $runDate = Carbon::parse($task[$this->getAssetFieldName(config('pureservice.repeating.field.date'))])->tz(config('app.timezone'));
                return $runDate == Carbon::today();
            });
        endif;
        return null;
    }

}
