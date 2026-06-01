<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Investor\InvestorController;

Route::middleware('auth:sanctum')->group(function () {
    Route::group(['prefix' => 'investor'], function () {
        Route::get('dealflow', [InvestorController::class, 'dealflow']);
        Route::get('programs', [InvestorController::class, 'programs']);
        Route::post('programs/{id}/enroll', [InvestorController::class, 'enrollProgram']);
        Route::get('analytics', [InvestorController::class, 'analytics']);
        Route::get('smes/{id}', [InvestorController::class, 'showSme']);
        Route::get('smes/{id}/dashboard', [InvestorController::class, 'smeDashboard']);
    });
});
