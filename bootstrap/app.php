<?php

use LaravelZero\Framework\Application;

$app = Application::configure(basePath: dirname(__DIR__))->create();
$app->register(\Barryvdh\DomPDF\ServiceProvider::class);
$app->configure('dompdf');

return $app;
