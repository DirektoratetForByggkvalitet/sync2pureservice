<?php

namespace App\Http\Controllers;

use PhpOffice\PhpSpreadsheet\{Spreadsheet, IOFactory};
use Illuminate\Support\Str;

class ExcelLookup extends Controller
{
    /**
     * Laster inn Excel-fila som er kilde for e-postadressene til kommunene
     * @return Collection   Collection-array over kommuner med e-postadresser
     */
    public static function loadData() {
        $spreadsheet = IOFactory::load(config('excellookup.file'));
        $data = collect($spreadsheet->getActiveSheet()->toArray(null, true, true, true));
        // Fjerner første linje
        $firstrow = $data->shift();

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
        $data = self::loadData();
        $result = $data->filter(function ($item, $key) use ($search) {
            if (preg_match('/'.$search.'.*/', $item[config('excellookup.field.name')])):
                return $item;
            endif;
        });
        if (count($result)) return $result->first();
        return false;
    }

    public static function findByKnr($knr) {
        $data = self::loadData();
        return $data->firstWhere(config('excellookup.field.email'), $knr);
    }
}
