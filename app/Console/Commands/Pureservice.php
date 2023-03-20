<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\{Tools, Pureservice as API};

class Pureservice extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'pureservice';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Viser oppsettet mot Pureservice';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {

    }
}
