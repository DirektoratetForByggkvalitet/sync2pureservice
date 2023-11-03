<?php

namespace App\Services;

use Psr\Http\Message\{RequestInterface, ResponseInterface};
use GuzzleHttp\{Client, HandlerStack, Middleware, RetryMiddleware, RequestOptions};
use Carbon\Carbon;
use Illuminate\Support\{Str, Arr, Facades\Storage};
use App\Models\{Company, User, Ticket, TicketCommunication};
use App\Services\PsApi;

/**
 * Denne klassen er utdatert og er i praksis faset ut til fordel for PsApi og PsAsset
 */
class Pureservice extends PsApi {}
