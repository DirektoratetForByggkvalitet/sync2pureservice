<?php

namespace App\Http\Controllers;

use PhpOffice\PhpSpreadsheet\{Spreadsheet, IOFactory};

class ExcelLookup extends Controller
{
    /**
     * Laster inn Excel-fila som er kilde for e-postadressene til kommunene
     * @return lazyCollection   Collection-array over kommuner med e-postadresser
     */
    protected static function loadData() {
        $spreadsheet = IOFactory::load(config('excellookup.file'));
        $data = collect($spreadsheet->getActiveSheet()->toArray(null, true, true, true));
        // Fjerner første linje
        $firstrow = $data->shift();
        $data->forget('G');
        return $data;

    }

    /**
     * Finner kommunen som passer med navnet man søker etter
     */
    public static function findByName($search) {
        $data = self::loadData();
        $result = $data->filter(function ($item, $key) use ($search) {
            if (preg_match('/'.$search.'.*/', $item['B'])):
                return $item;
            endif;
        });
        if (count($result)) return $result->first();
        return false;
    }

    public static function findByKnr($knr) {
        $data = self::loadData();
        return $data->firstWhere('A', $knr);
    }
}
