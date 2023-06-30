<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

class Tools {
    public const L1 = '';
    public const L2 = '> ';
    public const L3 = '  ';
    /**
     * Returnerer formatert tidspunkt til logging
     */
    public static function ts(): string {
        return '['.Carbon::now(config('app.timezone'))->toDateTimeLocalString().'] ';
    }

    public static function l1(): string {
        return self::L1;
    }

    public static function l2(): string {
        return self::L2;
    }

    public static function l3(): string {
        return self::L3;
    }

    public static function getPath (string $path, string $fileName = null): string {
        //
        if (Str::startsWith($path, '/')) return $fileName ? $path.'/'.$fileName : $path;

        if ($fileName):
            return Storage::path($path, $fileName);
        else:
            return Storage::path($path);
        endif;
    }
}
