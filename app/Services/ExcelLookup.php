<?php

namespace App\Services;

use PhpOffice\PhpSpreadsheet\{Spreadsheet, IOFactory};
use Illuminate\Support\Str;

class ExcelLookup {

    protected $data;

    public function __construct()
    {
        $this->data = self::loadData();
    }

    public function findName($name) {
        return $this->data->firstWhere(config('excellookup.field.name'), $name);
    }

    public function findKnr($knr) {
        return $this->data->firstWhere(config('excellookup.map.A'), $knr);
    }

    public function getData() {
        return $this->data;
    }
    /**
     * Laster inn Excel-fila som er kilde for e-postadressene til kommunene
     * @return Collection   Collection-array over kommuner med e-postadresser
     */
    public static function loadData() {
        if (!file_exists(config('excellookup.file')) || config('excellookup.file', false) == false):
            // Slå av funksjonaliteten
            return false;
        endif;
        $data = collect(IOFactory::load(config('excellookup.file'))->getActiveSheet()->toArray(null, true, true, true));
        // Fjerner første linje
        $data->shift();

        // Legger til verdier i config
        config([
            'excellookup.field.name' => config('excellookup.map.B'),
            'excellookup.field.email' => config('excellookup.map.F')
        ]);
        // Mapper om A, B, C osv til vettige navn etter innstillinger i config
        return $data->map(function ($item, $key) {
            return [
                config('excellookup.map.B') => Str::upper($item['B']),
                config('excellookup.map.F') => Str::lower($item['F']),
                config('excellookup.map.C') => $item['C'],
                config('excellookup.map.D') => $item['D'],
                config('excellookup.map.E') => $item['E'],
                config('excellookup.map.A') => $item['A'],
            ];
        });
    }

    /**
     * Finner kommunen som passer med navnet man søker etter
     */
    public static function findByName($search) {
        if ($data = self::loadData()):
            return $data->firstWhere(config('excellookup.field.name'), $search);
            /*$result = $data->filter(function ($item, $key) use ($search) {
                if (Str::contains($item[config('excellookup.field.name')], $search, true)):
                    return $item;
                endif;
            });
            if (count($result)) return $result->first();*/
        endif;
        return false;
    }

    public static function findByKnr($knr) {
        if ($data = self::loadData()):
            return $data->firstWhere(config('excellookup.map.A'), $knr);
        endif;
        return false;
    }
}
