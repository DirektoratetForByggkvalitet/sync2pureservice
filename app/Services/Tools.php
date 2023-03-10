<?php

namespace App\Services;

use Carbon\Carbon;

class Tools {
    /**
     * Returnerer formatert tidspunkt til logging
     */
    public static function ts(): string {
        return '['.Carbon::now(config('app.timezone'))->toDateTimeLocalString().'] ';
    }

    public static function l1(): string {
        return '';
    }

    public static function l2(): string {
        return '> ';
    }

    public static function l3(): string {
        return '  ';
    }
}
