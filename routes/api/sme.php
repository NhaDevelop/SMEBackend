<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Sme\SmeDashboardController;
use App\Http\Controllers\Sme\SmeAssessmentController;
use App\Http\Controllers\Sme\GoalController;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('sme/dashboard', [SmeDashboardController::class, 'index']);
    Route::get('sme/profile', [SmeDashboardController::class, 'profile']);

    Route::get('sme/goals', [GoalController::class, 'index']);
    Route::get('sme/goals/{id}', [GoalController::class, 'show']);
    Route::get('sme/goals/{id}/proof', [GoalController::class, 'downloadProof']);
    Route::post('sme/goals', [GoalController::class, 'store']);
    Route::patch('sme/goals/{id}', [GoalController::class, 'update']);
    Route::patch('sme/goals/{id}/verify', [GoalController::class, 'verifyGoal']);
    Route::patch('sme/goals/{id}/reject', [GoalController::class, 'rejectGoal']);
    Route::delete('sme/goals/{id}', [GoalController::class, 'destroy']);

    Route::group(['prefix' => 'sme'], function () {
        Route::get('enrolled-programs', [SmeAssessmentController::class, 'enrolledPrograms']);
        Route::get('templates', [SmeAssessmentController::class, 'templates']);
        Route::get('questions', [SmeAssessmentController::class, 'questions']);
        Route::get('settings', [SmeAssessmentController::class, 'frameworkSettings']);
        Route::get('sectors', [SmeAssessmentController::class, 'sectors']);
        Route::get('programs', [SmeAssessmentController::class, 'programs']);
        Route::get('programs/{id}/participants', [SmeAssessmentController::class, 'participants']);
    });
});
