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

    public static function human_filesize($bytes, $decimals = 2): string {
        $sz = 'BKMGTP';
        $factor = floor((strlen($bytes) - 1) / 3);
        return sprintf("%.{$decimals}f", $bytes / pow(1024, $factor)) . @$sz[$factor];
    }

    public static function dateFromEpochTime($ts): string {
        return Carbon::createFromTimestampMs($ts, config('app.timezone'))
            ->locale(config('app.locale'))
            ->toDateTimeString();
    }

    public static function nameFromEmail(string $email, bool $splitName = true): array|string {
        $data = [];
        $beforeAt = Str::before($email, '@');
        $domain = Str::after($email, '@');
        if (Str::contains($beforeAt, '.')):
            // Hvis e-postadressen f.eks. er ola.nordmann@gmail.com
            $data[0] = Str::Title(Str::beforeLast($beforeAt, '.'));
            $data[0] = Str::title(Str::replace('.', ' ', $data[0]));
            $data[1] = Str::ucfirst(Str::afterLast($beforeAt, '.'));
        else:
            $data[0] = $beforeAt;
            $data[1] = '-';
        endif;

        return $splitName ? $data : implode(' ', $data);

    }

    public static function reorderName(string $name, bool $splitName = true): array {
        $data = [];
        $nameArray = explode(', ', $name);
        $data[] = trim($nameArray[1]);
        $data[] = trim($nameArray[0]);
        return $splitName ? $data : implode(' ', $data);
    }

    public static function fileNameFromStoragePath(string $storagePath, bool $includeExt = true): string {
        $filename = Str::afterLast($storagePath, '/');
        return $includeExt ? $filename : Str::beforeLast($filename, '.');
    }

    public static function atomTs(string|int|false $ts = false): string {
        $t = $ts ? Carbon::parse($ts)->tz(config('app.timezone')) : Carbon::now(config('app.timezone'));
        return $t->toAtomString();
    }

    public static function cleanEmail(string $address): string {
        $address = Str::squish($address);
        $address = Str::remove([' ', ' '], $address);
        return $address;
    }
}
