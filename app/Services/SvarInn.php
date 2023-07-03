<?php

namespace App\Services;

use Psr\Http\Message\{RequestInterface, ResponseInterface};
use GuzzleHttp\{Client, HandlerStack, Middleware, RetryMiddleware, RequestOptions};
use Illuminate\Http\Client\Response;

class SvarInn extends API {


    /**
     * Se etter meldinger, returner array med meldinger, evt tomt array
     */
    public function sjekkForMeldinger(bool $returnResponse = false): array|Response {
        $uri = config('svarinn.urlHentForsendelser');
        $response = $this->apiGet($uri, true);
        return $returnResponse ? $response : $response->json();
    }

    /**
     * Setter en forsendelse som mottatt av mottakssystemet
     */
    public function settForsendelseMottatt(string $id) {
        $uri = $this->myConf('urlSettMottatt').'/'.$id;
        return $this->apiPost($uri);
    }

    /**
     * Merker en forsendelse som feilet
     */
    public function settForsendelseFeilet(string $id, bool $permanent = false, string|null $melding = null): bool {
        $uri = $this->myConf('urlMottakFeilet').'/'.$id;

        $melding = $melding == null ? 'En feil oppsto under innhenting.': $melding;
        $body = [
            'feilmelding' => $melding,
            'permanent' => $permanent,
        ];

        return $this->apiPost($uri, $body, null, null, true);
    }

}
