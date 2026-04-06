<?php
use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\AuditLogController;

Route::get('sectors', [\App\Http\Controllers\Admin\SectorController::class, 'index']);
Route::get('programs', [\App\Http\Controllers\Admin\ProgramController::class, 'index']);
Route::get('programs/{id}', [\App\Http\Controllers\Admin\ProgramController::class, 'show']);

Route::group(['prefix' => 'auth'], function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);

    // Protected routes
    Route::middleware('auth:api')->group(
        function () {
            Route::get('profile', [AuthController::class, 'profile']);
            Route::post('logout', [AuthController::class, 'logout']);
            Route::post('refresh', [AuthController::class, 'refresh']);
            Route::patch('profile', [\App\Http\Controllers\ProfileController::class, 'updateGeneralProfile']);
            Route::patch('sme/profile', [\App\Http\Controllers\ProfileController::class, 'updateSme']);
            Route::patch('investor/profile', [\App\Http\Controllers\ProfileController::class, 'updateInvestor']);
        }
    );
});
Route::group(['prefix' => 'admin', 'middleware' => ['auth:api', 'role:ADMIN']], function () {
    Route::get('users/pending', [UserController::class, 'fetchPendingUsers']);
    Route::get('users', [UserController::class, 'getApprovedUsers']);
    Route::post('users', [UserController::class, 'store']);
    Route::get('audit-logs', [AuditLogController::class, 'index']);
    Route::get('users/{id}', [UserController::class, 'show']);
    Route::patch('users/{id}/status', [UserController::class, 'updateStatus']);
    Route::patch('users/{id}/role', [UserController::class, 'updateRole']);
    Route::post('users/{id}/reset-password', [UserController::class, 'resetPassword']);
    Route::delete('users/{id}', [UserController::class, 'destroy']);

    // New API Resources and Endpoints
    Route::apiResource('sectors', \App\Http\Controllers\Admin\SectorController::class);

    Route::get('templates/active', [\App\Http\Controllers\Admin\TemplateController::class, 'active']);
    Route::patch('templates/{id}/status', [\App\Http\Controllers\Admin\TemplateController::class, 'updateStatus']);
    Route::apiResource('templates', \App\Http\Controllers\Admin\TemplateController::class);
    Route::apiResource('pillars', \App\Http\Controllers\Admin\PillarController::class);

    Route::get('questions', [\App\Http\Controllers\Admin\QuestionController::class, 'index']);
    Route::post('questions', [\App\Http\Controllers\Admin\QuestionController::class, 'store']);
    Route::put('questions/{id}', [\App\Http\Controllers\Admin\QuestionController::class, 'update']);
    Route::delete('questions', [\App\Http\Controllers\Admin\QuestionController::class, 'destroy']);

    Route::get('programs', [\App\Http\Controllers\Admin\ProgramController::class, 'index']);
    Route::get('programs/{id}', [\App\Http\Controllers\Admin\ProgramController::class, 'show']);
    Route::post('programs', [\App\Http\Controllers\Admin\ProgramController::class, 'store']);
    Route::put('programs/{id}', [\App\Http\Controllers\Admin\ProgramController::class, 'update']);
    Route::patch('programs/{id}', [\App\Http\Controllers\Admin\ProgramController::class, 'update']);
    Route::patch('programs/{id}/status', [\App\Http\Controllers\Admin\ProgramController::class, 'updateStatus']);
    Route::delete('programs/{id}', [\App\Http\Controllers\Admin\ProgramController::class, 'destroy']);
    Route::post('programs/enroll', [\App\Http\Controllers\Admin\ProgramController::class, 'enrollSmes']);
    Route::patch('programs/enrollments/status', [\App\Http\Controllers\Admin\ProgramController::class, 'updateEnrollmentStatus']);

    // Admin explicit participant fetching
    Route::get('programs/{id}/participants', [\App\Http\Controllers\Admin\ProgramController::class, 'participants']);

    Route::get('dashboard', [\App\Http\Controllers\Admin\DashboardController::class, 'index']);
    Route::get('smes', [UserController::class, 'getSmesData']);
    Route::get('smes/{id}', [\App\Http\Controllers\Admin\SmeManagementController::class, 'show']);
    Route::get('smes/{id}/dashboard', [\App\Http\Controllers\Admin\SmeManagementController::class, 'dashboard']);
    Route::get('verification-requests', [\App\Http\Controllers\Admin\VerificationRequestController::class, 'index']);
    Route::patch('verification-requests/{id}', [\App\Http\Controllers\Admin\VerificationRequestController::class, 'update']);
    Route::get('settings', [\App\Http\Controllers\Admin\SettingController::class, 'index']);
    Route::post('settings', [\App\Http\Controllers\Admin\SettingController::class, 'store']);

    // Debug endpoints for assessment scoring verification
    Route::get('debug/pillars', [\App\Http\Controllers\Admin\AssessmentDebugController::class, 'checkPillars']);
    Route::get('debug/assessments/score-check', [\App\Http\Controllers\Admin\AssessmentDebugController::class, 'checkAllAssessments']);
    Route::get('debug/assessment/{id}', [\App\Http\Controllers\Admin\AssessmentDebugController::class, 'debug']);

    // Reports endpoints
    Route::get('reports/programs', [\App\Http\Controllers\Admin\ReportsController::class, 'programs']);
    Route::get('reports/smes', [\App\Http\Controllers\Admin\ReportsController::class, 'smes']);
    Route::get('reports/scores', [\App\Http\Controllers\Admin\ReportsController::class, 'scores']);
    Route::get('reports/logs', [\App\Http\Controllers\Admin\ReportsController::class, 'logs']);
    Route::get('reports/export', [\App\Http\Controllers\Admin\ReportsController::class, 'export']);
});

// Public report download routes — token auth is handled inside the controller
Route::get('admin/reports/readiness', [\App\Http\Controllers\Admin\ReportsController::class, 'readiness']);
Route::get('admin/reports/portfolio', [\App\Http\Controllers\Admin\ReportsController::class, 'portfolio']);
// ✅ NEW: Poll for background batch report status (returns 'processing' | 'ready' | 'failed')
Route::get('admin/reports/status', [\App\Http\Controllers\Admin\ReportsController::class, 'reportStatus']);


// SME & Investor Shared/Specific Routes (Protected by Auth)
Route::middleware('auth:api')->group(function () {
    // SME Analytics & Dashboard
    Route::get('sme/dashboard', [\App\Http\Controllers\SmeDashboardController::class, 'index']);
    Route::get('sme/profile', [\App\Http\Controllers\SmeDashboardController::class, 'profile']);

    // Assessment Engine
    Route::group(
        ['prefix' => 'assessment'],
        function () {
            Route::get('questions', [App\Http\Controllers\AssessmentController::class, 'getQuestions']);
            Route::post('start', [App\Http\Controllers\AssessmentController::class, 'start']);
            Route::post('{id}/submit', [App\Http\Controllers\AssessmentController::class, 'submit']);
            Route::get('history', [App\Http\Controllers\AssessmentController::class, 'history']);
        }
    );

    // Investor Marketplace (Dealflow)
    Route::group(
        ['prefix' => 'investor'],
        function () {
            Route::get('dealflow', [App\Http\Controllers\InvestorController::class, 'dealflow']);
            Route::get('programs', [App\Http\Controllers\InvestorController::class, 'programs']);
            Route::post('programs/{id}/enroll', [App\Http\Controllers\InvestorController::class, 'enrollProgram']);
            Route::get('analytics', [App\Http\Controllers\InvestorController::class, 'analytics']);
            // Investor-accessible SME detail (read-only, no admin actions)
            Route::get('smes/{id}', [App\Http\Controllers\InvestorController::class, 'showSme']);
            Route::get('smes/{id}/dashboard', [App\Http\Controllers\InvestorController::class, 'smeDashboard']);
        }
    );

    // Shared Participants (SME / Investor)
    Route::get('programs/{id}/participants', [\App\Http\Controllers\Admin\ProgramController::class, 'participants']);

    // Programs Lifecycle for SME
    Route::post('programs/{id}/apply', [App\Http\Controllers\Admin\ProgramController::class, 'apply']);

    // SME Goal Tracking
    Route::get('sme/goals', [App\Http\Controllers\GoalController::class, 'index']);
    Route::get('sme/goals/{id}', [App\Http\Controllers\GoalController::class, 'show']);
    Route::post('sme/goals', [App\Http\Controllers\GoalController::class, 'store']);
    Route::patch('sme/goals/{id}', [App\Http\Controllers\GoalController::class, 'update']);
    Route::patch('sme/goals/{id}/verify', [App\Http\Controllers\GoalController::class, 'verifyGoal']);
    Route::patch('sme/goals/{id}/reject', [App\Http\Controllers\GoalController::class, 'rejectGoal']);
    Route::delete('sme/goals/{id}', [App\Http\Controllers\GoalController::class, 'destroy']);

    // Communication & Docs
    Route::get('messages', [App\Http\Controllers\MessageController::class, 'index']);
    Route::post('messages', [App\Http\Controllers\MessageController::class, 'store']);

    Route::get('notifications', [App\Http\Controllers\NotificationController::class, 'index']);
    Route::patch('notifications/{id}/read', [App\Http\Controllers\NotificationController::class, 'markAsRead']);

    Route::get('documents', [App\Http\Controllers\DocumentController::class, 'index']);
    Route::post('documents', [App\Http\Controllers\DocumentController::class, 'store']);
    Route::get('documents/{id}', [App\Http\Controllers\DocumentController::class, 'show']);
    Route::delete('documents/{id}', [App\Http\Controllers\DocumentController::class, 'destroy']);

    // Shared Data (Authenticated but role-neutral)
    Route::get('settings', [\App\Http\Controllers\Admin\SettingController::class, 'index']);
    Route::get('users/discovery', [\App\Http\Controllers\Admin\UserController::class, 'getApprovedUsers']);

    // Program Comments / Forum
    Route::get('programs/{id}/comments', [App\Http\Controllers\ProgramCommentController::class, 'index']);
    Route::post('programs/{id}/comments', [App\Http\Controllers\ProgramCommentController::class, 'store']);
    Route::delete('programs/{programId}/comments/{commentId}', [App\Http\Controllers\ProgramCommentController::class, 'destroy']);

    // SME Assessment Authorized Access
    Route::group(
        ['prefix' => 'sme'],
        function () {
            Route::get('enrolled-programs', [\App\Http\Controllers\SmeAssessmentController::class, 'enrolledPrograms']);
            Route::get('templates', [\App\Http\Controllers\SmeAssessmentController::class, 'templates']);
            Route::get('questions', [\App\Http\Controllers\SmeAssessmentController::class, 'questions']);
            Route::get('settings', [\App\Http\Controllers\SmeAssessmentController::class, 'frameworkSettings']);
            Route::get('sectors', [\App\Http\Controllers\SmeAssessmentController::class, 'sectors']);
            Route::get('programs', [\App\Http\Controllers\SmeAssessmentController::class, 'programs']);
            Route::get('programs/{id}/participants', [\App\Http\Controllers\SmeAssessmentController::class, 'participants']);
        }
    );
});