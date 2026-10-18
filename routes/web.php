<?php

use App\Http\Controllers\DespachoController;
use App\Http\Controllers\EdificioController;
use App\Http\Controllers\JuegoController;
use App\Http\Controllers\MisionController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\VehiculoController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
    ]);
});

Route::get('/dashboard', [JuegoController::class, 'mapa'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::post('/edificios', [EdificioController::class, 'store'])->name('edificios.store');
    Route::post('/vehiculos', [VehiculoController::class, 'store'])->name('vehiculos.store');

    Route::get('/misiones', [MisionController::class, 'index'])->name('misiones.index');
    Route::get('/misiones/{mision}', [MisionController::class, 'show'])->name('misiones.show');

    Route::post('/despachos', [DespachoController::class, 'store'])->name('despachos.store');
    Route::delete('/despachos/{despacho}', [DespachoController::class, 'destroy'])->name('despachos.destroy');
});

require __DIR__.'/auth.php';
