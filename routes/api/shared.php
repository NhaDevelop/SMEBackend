<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Sme\AssessmentController;
use App\Http\Controllers\Admin\ProgramController;
use App\Http\Controllers\Shared\MessageController;
use App\Http\Controllers\Shared\NotificationController;
use App\Http\Controllers\Shared\DocumentController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Shared\ProgramCommentController;

Route::middleware('auth:sanctum')->group(function () {
    Route::group(['prefix' => 'assessment'], function () {
        Route::get('questions', [AssessmentController::class, 'getQuestions']);
        Route::post('start', [AssessmentController::class, 'start']);
        Route::middleware('throttle:5,1')->post('{id}/submit', [AssessmentController::class, 'submit']);
        Route::get('history', [AssessmentController::class, 'history']);
    });

    Route::get('programs/{id}/participants', [ProgramController::class, 'participants']);
    Route::post('programs/{id}/apply', [ProgramController::class, 'apply']);

    Route::get('messages', [MessageController::class, 'index']);
    Route::post('messages', [MessageController::class, 'store']);

    Route::get('notifications', [NotificationController::class, 'index']);
    Route::patch('notifications/{id}/read', [NotificationController::class, 'markAsRead']);

    Route::get('documents', [DocumentController::class, 'index']);
    Route::post('documents', [DocumentController::class, 'store']);
    Route::get('documents/{id}', [DocumentController::class, 'show']);
    Route::get('documents/{id}/download', [DocumentController::class, 'download']);
    Route::delete('documents/{id}', [DocumentController::class, 'destroy']);

    Route::get('settings', [SettingController::class, 'index']);
    Route::get('users/discovery', [UserController::class, 'getApprovedUsers']);

    Route::get('programs/{id}/comments', [ProgramCommentController::class, 'index']);
    Route::post('programs/{id}/comments', [ProgramCommentController::class, 'store']);
    Route::delete('programs/{programId}/comments/{commentId}', [ProgramCommentController::class, 'destroy']);
});
