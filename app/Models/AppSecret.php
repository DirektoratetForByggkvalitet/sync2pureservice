<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\{Model, Collection, Factories\HasFactory};

class AppSecret extends Model {
    use HasFactory;

    protected $primaryKey = 'internal_id';
    protected $casts = [
        'startDateTime' => 'datetime',
        'endDateTime' => 'datetime',
    ];

    protected $hidden = [
        'updated_at',
        'created_at',
    ];

    protected $fillable = [
        'id',
        'appId',
        'appName',
        'keyType',
        'displayName',
        'startDateTime',
        'endDateTime',
    ];

    public function displayType(bool $the = false): string {
        $type = $this->type == 'password' ? 'Passord' : 'Sertifikat';
        $type = $the ? $type.'et' : $type;
        return $type;
    }

    public static function getExpires(Carbon|null $dt = null, bool $limitToToday = true): Collection {
        $dt = $dt ? $dt : now('UTC')->addDays(config('appsecret.notify.days'));
        $query = self::where('endDateTime', '<=', $dt);
        if ($limitToToday):
            $query->where('endDateTime', '>=', now('UTC')->startOfDay());
        endif;
        return $query->get();
    }

    public function getCustomerReference() {
        return config('appsecret.notify.refPrefix') . $this->appId;
    }
}
