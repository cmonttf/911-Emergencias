<?php

use App\Dominio\Misiones\Jobs\GenerarMisionesJob;
use App\Dominio\Misiones\Jobs\ReconciliarEventosVencidosJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Genera misiones para los jugadores con cupo libre (sección 5 del ERS).
Schedule::job(new GenerarMisionesJob)->everyMinute();

// Corrige vehículos o misiones con eventos vencidos sin procesar.
Schedule::job(new ReconciliarEventosVencidosJob)->everyFiveMinutes();
