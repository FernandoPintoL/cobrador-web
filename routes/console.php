<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Programar el procesamiento de entregas de créditos
Schedule::command('credits:process-scheduled-deliveries --notify')
    ->dailyAt('08:00')
    ->description('Procesar entregas programadas de créditos y enviar notificaciones');

// Verificar créditos atrasados para entrega
Schedule::command('credits:process-scheduled-deliveries --notify')
    ->dailyAt('17:00')
    ->description('Verificar créditos atrasados para entrega');
