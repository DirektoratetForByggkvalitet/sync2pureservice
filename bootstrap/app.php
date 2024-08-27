<?php

use LaravelZero\Framework\Application;

$app->register(\Barryvdh\DomPDF\ServiceProvider::class);
$app->configure('dompdf');
return Application::configure(basePath: dirname(__DIR__))->create();
