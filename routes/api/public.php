<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\SectorController;
use App\Http\Controllers\Admin\ProgramController;

Route::get('sectors', [SectorController::class, 'index']);
Route::get('programs', [ProgramController::class, 'index']);
Route::get('programs/{id}', [ProgramController::class, 'show']);
