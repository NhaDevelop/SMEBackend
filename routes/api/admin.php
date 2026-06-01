<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\SectorController;
use App\Http\Controllers\Admin\TemplateController;
use App\Http\Controllers\Admin\PillarController;
use App\Http\Controllers\Admin\QuestionController;
use App\Http\Controllers\Admin\ProgramController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\SmeManagementController;
use App\Http\Controllers\Admin\VerificationRequestController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\AssessmentDebugController;
use App\Http\Controllers\Admin\ReportsController;

Route::group(['prefix' => 'admin', 'middleware' => ['auth:sanctum', 'role:ADMIN']], function () {
    Route::get('dashboard', [DashboardController::class, 'index']);

    // User Management
    Route::get('users/pending', [UserController::class, 'fetchPendingUsers']);
    Route::get('users', [UserController::class, 'getApprovedUsers']);
    Route::post('users', [UserController::class, 'store']);
    Route::get('users/{id}', [UserController::class, 'show']);
    Route::get('users/{id}/document', [UserController::class, 'downloadRegistrationDocument']);
    Route::patch('users/{id}/status', [UserController::class, 'updateStatus']);
    Route::patch('users/{id}/role', [UserController::class, 'updateRole']);
    Route::post('users/{id}/reset-password', [UserController::class, 'resetPassword']);
    Route::delete('users/{id}', [UserController::class, 'destroy']);

    // SME Management
    Route::get('smes', [UserController::class, 'getSmesData']);
    Route::get('smes/{id}', [SmeManagementController::class, 'show']);
    Route::get('smes/{id}/dashboard', [SmeManagementController::class, 'dashboard']);
    Route::get('smes/{id}/programs', [UserController::class, 'smePrograms']);

    // Audit Logs
    Route::get('audit-logs', [AuditLogController::class, 'index']);

    // Assessment Framework
    Route::apiResource('sectors', SectorController::class);
    Route::get('templates/active', [TemplateController::class, 'active']);
    Route::post('templates/{id}/duplicate', [TemplateController::class, 'duplicate']);
    Route::patch('templates/{id}/status', [TemplateController::class, 'updateStatus']);
    Route::apiResource('templates', TemplateController::class);
    Route::apiResource('pillars', PillarController::class);
    Route::get('questions', [QuestionController::class, 'index']);
    Route::post('questions', [QuestionController::class, 'store']);
    Route::put('questions/{id}', [QuestionController::class, 'update']);
    Route::delete('questions', [QuestionController::class, 'destroy']);

    // Program Management
    Route::get('programs', [ProgramController::class, 'index']);
    Route::get('programs/{id}', [ProgramController::class, 'show']);
    Route::post('programs', [ProgramController::class, 'store']);
    Route::put('programs/{id}', [ProgramController::class, 'update']);
    Route::patch('programs/{id}', [ProgramController::class, 'update']);
    Route::patch('programs/{id}/status', [ProgramController::class, 'updateStatus']);
    Route::delete('programs/{id}', [ProgramController::class, 'destroy']);
    Route::post('programs/enroll', [ProgramController::class, 'enrollSmes']);
    Route::patch('programs/enrollments/status', [ProgramController::class, 'updateEnrollmentStatus']);
    Route::get('programs/{id}/participants', [ProgramController::class, 'participants']);

    // Verification Requests
    Route::get('verification-requests', [VerificationRequestController::class, 'index']);
    Route::patch('verification-requests/{id}', [VerificationRequestController::class, 'update']);

    // Settings
    Route::get('settings', [SettingController::class, 'index']);
    Route::post('settings', [SettingController::class, 'store']);

    // Debug endpoints
    Route::get('debug/pillars', [AssessmentDebugController::class, 'checkPillars']);
    Route::get('debug/assessments/score-check', [AssessmentDebugController::class, 'checkAllAssessments']);
    Route::get('debug/assessment/{id}', [AssessmentDebugController::class, 'debug']);

    // Reports
    Route::get('reports/programs', [ReportsController::class, 'programs']);
    Route::get('reports/smes', [ReportsController::class, 'smes']);
    Route::get('reports/scores', [ReportsController::class, 'scores']);
    Route::get('reports/logs', [ReportsController::class, 'logs']);
});

// Public report download routes
Route::get('admin/reports/readiness', [ReportsController::class, 'readiness']);
Route::get('admin/reports/portfolio', [ReportsController::class, 'portfolio']);
Route::get('admin/reports/status', [ReportsController::class, 'reportStatus']);
Route::get('admin/reports/export', [ReportsController::class, 'export']);
