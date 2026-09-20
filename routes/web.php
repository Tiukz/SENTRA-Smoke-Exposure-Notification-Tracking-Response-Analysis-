<?php

use App\Http\Controllers\HazeController;
use App\Http\Controllers\RouteController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/sentra', [HazeController::class, 'index'])->name('sentra.dashboard');
Route::get('/sentra/demo', [HazeController::class, 'demo'])->name('sentra.demo');
Route::post('/sentra/simulate', [HazeController::class, 'simulate'])->name('sentra.simulate');
Route::post('/sentra/data-mode', [HazeController::class, 'switchDataMode'])->name('sentra.data-mode');
Route::post('/sentra/route', [RouteController::class, 'calculate'])->name('sentra.route');
Route::post('/sentra/select-hotspot', [HazeController::class, 'selectHotspot'])->name('sentra.select-hotspot');
Route::get('/sentra/response-plan/{facility}', [HazeController::class, 'responsePlan'])
    ->whereNumber('facility')
    ->name('sentra.response-plan');
