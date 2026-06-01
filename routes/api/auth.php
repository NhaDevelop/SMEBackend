<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\ProfileController;
use App\Http\Controllers\Auth\EmailVerificationController;

Route::get('auth/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
    ->name('email.verify');

Route::group(['prefix' => 'auth'], function () {
    Route::middleware('throttle:10,1')->group(function () {
        Route::post('register', [AuthController::class, 'register']);
        Route::post('login', [AuthController::class, 'login']);
    });

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('profile', [AuthController::class, 'profile']);
        Route::post('logout', [AuthController::class, 'logout']);
        Route::post('refresh', [AuthController::class, 'refresh']);
        Route::patch('profile', [ProfileController::class, 'updateGeneralProfile']);
        Route::patch('sme/profile', [ProfileController::class, 'updateSme']);
        Route::patch('investor/profile', [ProfileController::class, 'updateInvestor']);
    });
});
