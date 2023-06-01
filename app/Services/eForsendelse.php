<?php

namespace App\Services;
use App\Services\API;

use function PHPSTORM_META\map;

class eForsendelse extends API {
    public function __construct() {
        parent::__construct();
        $this->prefix = $this->myConf('ip.prefix');
    }

    protected function setupClient(): void {
        $headers = [
            'Accept' => 'application/json',
            'Connection' => 'keep-alive',
            'Accept-Encoding' => 'gzip, deflate'
        ];
        if (config($this->cKey.'.ip.auth')):
            $headers['Authorization'] = $this->myConf('ip.user').
                ':'.
                $this->myConf('ip.password');
        endif;
        $this->setOptions([
            'headers' => $headers,
        ]);
    }

}
