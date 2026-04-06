<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateBatchReportJob;
use App\Models\Program;
use App\Models\ProgramEnrollment;
use App\Models\SmeProfile;
use App\Models\Assessment;
use App\Models\Pillar;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class ReportsController extends Controller
{
    use \App\Traits\AssessmentScoring;

    /**
     * Get all programs with SME count and average scores
     */
    public function programs(Request $request)
    {
        $cacheKey = 'report_programs_v1';

        $programs = Cache::remember($cacheKey, now()->addMinutes(10), function () {
            // ✅ FIX N+1: eager-load template + enrollments with their smeProfile & assessments in one shot
            $allPrograms = Program::with([
                'template',
                'enrollments' => fn($q) => $q->whereNotNull('sme_id'),
                'enrollments.smeProfile.user',
                'enrollments.smeProfile.assessments',
            ])->get();

            return $allPrograms->map(function ($program) {
                $enrollments          = $program->enrollments;
                $totalSmes            = $enrollments->count();
                $completedAssessments = 0;
                $totalScore           = 0;
                $smeScores            = [];

                foreach ($enrollments as $enrollment) {
                    $smeProfile = $enrollment->smeProfile;
                    if (!$smeProfile) continue;

                    // Already eager-loaded — no extra query
                    $assessment = $smeProfile->assessments
                        ->where('template_id', $program->template_id)
                        ->where('status', 'Completed')
                        ->sortByDesc('created_at')
                        ->first();

                    if ($assessment) {
                        $completedAssessments++;
                        $totalScore += $assessment->total_score;
                        $pillarScores = $this->calculatePillarScores($assessment);
                        $smeScores[] = [
                            'sme_id'       => $smeProfile->id,
                            'sme_name'     => $smeProfile->company_name,
                            'user_name'    => $smeProfile->user?->full_name,
                            'email'        => $smeProfile->user?->email,
                            'industry'     => $smeProfile->industry,
                            'assessment_id' => $assessment->id,
                            'total_score'  => $assessment->total_score,
                            'risk_level'   => $assessment->risk_level,
                            'completed_at' => $assessment->completed_at?->format('Y-m-d'),
                            'pillar_scores' => $pillarScores,
                        ];
                    }
                }

                return [
                    'id'                    => $program->id,
                    'name'                  => $program->name,
                    'description'           => $program->description,
                    'status'                => $program->status,
                    'sector'                => $program->sector,
                    'template_name'         => $program->template?->name,
                    'total_smes'            => $totalSmes,
                    'completed_assessments' => $completedAssessments,
                    'avg_score'             => $completedAssessments > 0 ? round($totalScore / $completedAssessments, 2) : 0,
                    'sme_scores'            => $smeScores,
                ];
            });
        });

        return $this->success($programs, 'Programs report retrieved successfully');
    }

    /**
     * GET /api/admin/reports/smes
     * Returns all SMEs with their scores. When program_id is supplied,
     * only SMEs enrolled in that program are returned, and their score is
     * the latest assessment for that program's template (0 if not yet assessed).
     */
    public function smes(Request $request)
    {
        $programId        = $request->input('program_id');
        $targetTemplateId = $programId ? Program::find($programId)?->template_id : null;

        // ✅ FIX N+1: eager-load assessments and enrollments up-front
        $query = SmeProfile::with([
            'user',
            'assessments',
            'enrollments.program',
        ]);

        if ($programId) {
            $enrolledSmeIds = ProgramEnrollment::where('program_id', $programId)
                ->whereNotNull('sme_id')
                ->pluck('sme_id');
            $query->whereIn('id', $enrolledSmeIds);
        }

        $smes = $query->get()->map(function ($sme) use ($programId, $targetTemplateId) {
            // ✅ Filter from already-loaded assessments — no extra DB queries
            $completedAssessments = $sme->assessments->where('status', 'Completed');

            $latestAssessment = $targetTemplateId
                ? $completedAssessments->where('template_id', $targetTemplateId)->sortByDesc('completed_at')->first()
                : $completedAssessments->sortByDesc('completed_at')->first();

            // Per-program scores list — also from eager-loaded data
            $programs = $sme->enrollments->map(function ($enrollment) use ($sme) {
                $program    = $enrollment->program;
                // Already in $sme->assessments — filter in PHP
                $assessment = $sme->assessments
                    ->where('template_id', $program?->template_id)
                    ->where('status', 'Completed')
                    ->sortByDesc('created_at')
                    ->first();

                return [
                    'program_id'        => $program?->id,
                    'program_name'      => $program?->name,
                    'enrollment_status' => $enrollment->status,
                    'enrollment_date'   => $enrollment->enrollment_date?->format('Y-m-d'),
                    'assessment_score'  => $assessment?->total_score,
                    'assessment_status' => $assessment?->status,
                    'completed_at'      => $assessment?->completed_at?->format('Y-m-d'),
                ];
            });

            $pillarScores = $latestAssessment ? $this->calculatePillarScores($latestAssessment) : [];

            return [
                'id'                  => $sme->id,
                'company_name'        => $sme->company_name,
                'user_name'           => $sme->user?->full_name,
                'email'               => $sme->user?->email,
                'phone'               => $sme->user?->phone,
                'industry'            => $sme->industry,
                'registration_number' => $sme->registration_number,
                'years_in_operation'  => $sme->years_in_operation,
                'total_employees'     => $sme->total_employees,
                'latest_score'        => $latestAssessment ? round((float) $latestAssessment->total_score, 1) : null,
                'latest_risk_level'   => $latestAssessment
                    ? $latestAssessment->risk_level
                    : ($targetTemplateId ? 'Not Assessed' : null),
                'last_assessed'       => $latestAssessment?->completed_at?->format('Y-m-d'),
                'programs'            => $programs,
                'pillar_scores'       => $pillarScores,
            ];
        });

        return $this->success($smes, 'SMEs report retrieved successfully');
    }

    /**
     * GET /api/admin/reports/scores
     * Score distribution and statistics, optionally scoped to a program.
     */
    public function scores(Request $request)
    {
        $programId        = $request->input('program_id');
        $targetTemplateId = $programId ? Program::find($programId)?->template_id : null;
        $cacheKey         = 'report_scores_' . ($programId ?? 'all');

        // ✅ Cache score distribution for 10 minutes to avoid repeated full-table scans
        $cached = Cache::remember($cacheKey, now()->addMinutes(10), function () use ($programId, $targetTemplateId) {
        $query = Assessment::where('status', 'Completed');
        if ($targetTemplateId) {
            $smeIds = ProgramEnrollment::where('program_id', $programId)
                ->whereNotNull('sme_id')->pluck('sme_id');
            $query->where('template_id', $targetTemplateId)->whereIn('sme_id', $smeIds);
        }
        $assessments = $query->get();

        $totalAssessments = $assessments->count();

        if ($totalAssessments === 0) {
            return $this->success([
                'total_assessments'  => 0,
                'avg_score'          => 0,
                'score_distribution' => [],
                'by_program'         => [],
                'by_pillar'          => [],
            ], 'No completed assessments found');
        }

        $avgScore = round($assessments->avg('total_score'), 2);

        $distribution = [
            'excellent'        => $assessments->where('total_score', '>=', 80)->count(),
            'good'             => $assessments->whereBetween('total_score', [60, 79.99])->count(),
            'average'          => $assessments->whereBetween('total_score', [40, 59.99])->count(),
            'needs_improvement'=> $assessments->where('total_score', '<', 40)->count(),
        ];

        $byProgram = Program::with(['template'])->get()->map(function ($program) {
            $smeIds = ProgramEnrollment::where('program_id', $program->id)->pluck('sme_id');
            $scores = Assessment::whereIn('sme_id', $smeIds)
                ->where('template_id', $program->template_id)
                ->where('status', 'Completed')
                ->pluck('total_score');
            return [
                'program_id'        => $program->id,
                'program_name'      => $program->name,
                'total_assessments' => $scores->count(),
                'avg_score'         => $scores->count() > 0 ? round($scores->avg(), 2) : 0,
                'min_score'         => $scores->count() > 0 ? round($scores->min(), 2) : 0,
                'max_score'         => $scores->count() > 0 ? round($scores->max(), 2) : 0,
            ];
        });

        $pillars = Pillar::all();
        $byPillar = [];
        foreach ($pillars as $pillar) {
            $pillarScores = [];
            foreach ($assessments as $assessment) {
                $snapshot        = $assessment->questions_snapshot ?? [];
                $pillarQuestions = collect($snapshot)->where('pillar_id', $pillar->id);
                if ($pillarQuestions->count() > 0) {
                    $pillarScores[] = $pillarQuestions->avg('score_awarded');
                }
            }
            $byPillar[] = [
                'pillar_id'   => $pillar->id,
                'pillar_name' => $pillar->name,
                'avg_score'   => count($pillarScores) > 0 ? round(array_sum($pillarScores) / count($pillarScores), 2) : 0,
                'weight'      => $pillar->weight,
            ];
        }

        return [
            'total_assessments'  => $totalAssessments,
            'avg_score'          => $avgScore,
            'score_distribution' => $distribution,
            'by_program'         => $byProgram,
            'by_pillar'          => $byPillar,
        ];
        }); // end Cache::remember

        return $this->success($cached, 'Scores report retrieved successfully');
    }

    /**
     * Export report data (PDF generation placeholder)
     */
    public function export(Request $request)
    {
        $type = $request->input('type', 'sme'); // 'sme' or 'program'
        $id = $request->input('id');
        $format = $request->input('format', 'pdf'); // 'pdf' or 'excel'
        $user = auth('api')->user();

        if ($type === 'sme' && $id) {
            // Generate individual SME report
            $sme = SmeProfile::with(['user', 'assessments'])->where('user_id', $id)->firstOrFail();
            $data = $this->generateSmeReportData($sme);

            \App\Models\AuditLog::create([
                'user_id' => $user->id ?? null,
                'action' => 'EXPORTED_DATA',
                'target_entity' => 'SmeProfile',
                'target_id' => $sme->id,
                'details' => json_encode([
                    'report_type' => 'CSV Export',
                    'sme_name' => $sme->company_name,
                    'format' => $format
                ]),
                'ip_address' => $request->ip()
            ]);
            
            return $this->success([
                'type' => 'sme',
                'sme_id' => $id,
                'sme_name' => $sme->company_name,
                'format' => $format,
                'data' => $data,
                'download_url' => null // Placeholder for actual PDF generation
            ], 'SME report data prepared for export');
        }

        if ($type === 'program' && $id) {
            // Generate program report
            $program = Program::with(['template', 'enrollments.smeProfile'])->findOrFail($id);
            $data = $this->generateProgramReportData($program);
            
            return $this->success([
                'type' => 'program',
                'program_id' => $id,
                'program_name' => $program->name,
                'format' => $format,
                'data' => $data,
                'download_url' => null // Placeholder for actual PDF generation
            ], 'Program report data prepared for export');
        }

        return $this->error('Invalid export parameters. Provide type and id.', 400);
    }

    /**
     * Get audit/report logs
     */
    public function logs(Request $request)
    {
        $logs = \App\Models\AuditLog::with('user')
            ->latest()
            ->limit(100)
            ->get()
            ->map(function ($log) {
                return [
                    'id' => $log->id,
                    'action' => $log->action,
                    'user' => $log->user?->full_name ?? 'System',
                    'entity' => $log->target_entity,
                    'entity_id' => $log->target_id,
                    'details' => json_decode($log->details, true),
                    'ip_address' => $log->ip_address,
                    'created_at' => $log->created_at->format('Y-m-d H:i:s')
                ];
            });

        return $this->success($logs, 'Report logs retrieved successfully');
    }

    /**
     * Generate Investment Readiness Report for an SME (HTML/PDF ready)
     */
    public function readiness(Request $request)
    {
        // Check for token in query param (for new-tab downloads) or normal auth
        $token = $request->input('token');
        $smeId = $request->input('smeId');
        $programId = $request->input('programId'); // Optional: scope to a specific program

        // If no smeId, generate portfolio report instead
        if (!$smeId) {
            return $this->portfolio($request);
        }

        // If token provided via query, validate it — allow both ADMIN and INVESTOR
        if ($token) {
            try {
                /** @var \Tymon\JWTAuth\JWTGuard $guard */
                $guard = Auth::guard('api');
                $user = $guard->setToken($token)->authenticate();
                if (!$user || !in_array($user->role, ['ADMIN', 'INVESTOR'])) {
                    return $this->error('Unauthorized', 401);
                }
            } catch (\Exception $e) {
                return $this->error('Invalid token', 401);
            }
        } else if (!auth('api')->check()) {
            return $this->error('Authentication required', 401);
        }

        $user = auth('api')->user() ?? ($token ? $user : null);
        
        // The frontend passes the User ID as smeId
        $sme = SmeProfile::with(['user', 'assessments'])->where('user_id', '=', $smeId)->firstOrFail();
        $program = $programId ? Program::with('template')->find($programId) : null;
        $data = $this->generateSmeReportData($sme, $program);

        // ✅ Log report generation
        \App\Models\AuditLog::create([
            'user_id' => $user->id ?? null,
            'action' => 'GENERATED_REPORT',
            'target_entity' => 'SmeProfile',
            'target_id' => $sme->id,
            'details' => json_encode([
                'report_type' => 'Investment Readiness Report',
                'sme_name' => $sme->company_name,
                'program_id' => $programId,
                'format' => 'PDF'
            ]),
            'ip_address' => $request->ip()
        ]);

        // Return as HTML that can be printed to PDF
        $html = $this->generateReadinessReportHtml($data, $program);
        
        return response($html, 200, [
            'Content-Type' => 'text/html',
            'Content-Disposition' => 'inline; filename="readiness-report-' . $smeId . '.html"'
        ]);
    }

    /**
     * Generate Portfolio Comparison Report
     */
    public function portfolio(Request $request)
    {
        // Check for token in query param (for new-tab downloads) or normal auth
        $token = $request->input('token');
        $programId = $request->input('programId');
        
        if ($token) {
            try {
                /** @var \Tymon\JWTAuth\JWTGuard $guard */
                $guard = Auth::guard('api');
                $user = $guard->setToken($token)->authenticate();
                if (!$user || !in_array($user->role, ['ADMIN', 'INVESTOR'])) {
                    return $this->error('Unauthorized', 401);
                }
            } catch (\Exception $e) {
                return $this->error('Invalid token', 401);
            }
        } else if (!auth('api')->check()) {
            return $this->error('Authentication required', 401);
        }

        $user = auth('api')->user() ?? ($token ? $user : null);

        $program = $programId ? Program::with('template')->find($programId) : null;

        // ✅ For large batches (> 20 SMEs), dispatch to background queue instead of blocking
        $smeCount = $program
            ? ProgramEnrollment::where('program_id', $program->id)->whereNotNull('sme_id')->count()
            : SmeProfile::count();

        if ($smeCount > 20) {
            // Generate a unique key so the frontend can poll for status
            $reportKey = 'batch_report_' . Str::uuid();
            Cache::put($reportKey . '_status', 'processing', now()->addMinutes(30));

            GenerateBatchReportJob::dispatch($reportKey, $programId)
                ->onQueue('default');

            return $this->success([
                'async'      => true,
                'report_key' => $reportKey,
                'message'    => 'Report is being generated in the background. Poll /api/admin/reports/status?key=' . $reportKey . ' for updates.',
            ], 'Large report queued for background processing');
        }

        // ✅ For small batches: generate inline (fast, no queue needed)
        if ($program) {
            $smeIds  = ProgramEnrollment::where('program_id', $program->id)->whereNotNull('sme_id')->pluck('sme_id');
            $allSmes = SmeProfile::with(['user', 'assessments.template'])->whereIn('id', $smeIds)->get();
        } else {
            $allSmes = SmeProfile::with(['user', 'assessments.template'])->get();
        }

        $allSmeData = $allSmes->map(fn($sme) => $this->generateSmeReportData($sme, $program));
        $html       = $this->generatePortfolioReportHtml($allSmeData, $program);

        // ✅ Log portfolio report generation
        \App\Models\AuditLog::create([
            'user_id' => $user->id ?? null,
            'action' => 'GENERATED_PORTFOLIO_REPORT',
            'target_entity' => 'Program',
            'target_id' => $programId,
            'details' => json_encode([
                'report_type' => 'Portfolio Comparison',
                'program_name' => $program ? $program->name : 'All Programs',
                'format' => 'PDF'
            ]),
            'ip_address' => $request->ip()
        ]);

        return response($html, 200, [
            'Content-Type'        => 'text/html',
            'Content-Disposition' => 'inline; filename="portfolio-report.html"',
        ]);
    }

    /**
     * GET /api/admin/reports/status?key={key}
     * Poll this endpoint to check if a background batch report is ready.
     */
    public function reportStatus(Request $request)
    {
        $key    = $request->input('key');
        $status = Cache::get($key . '_status', 'not_found');

        if ($status === 'ready') {
            // Data is in cache — return it and clean up
            $data = Cache::get($key . '_data', []);
            Cache::forget($key . '_status');
            Cache::forget($key . '_data');

            // Build and return the HTML report
            $programId = $request->input('programId');
            $program   = $programId ? Program::with('template')->find($programId) : null;
            $html      = $this->generatePortfolioReportHtml(collect($data), $program);

            return response($html, 200, [
                'Content-Type'        => 'text/html',
                'Content-Disposition' => 'inline; filename="portfolio-report.html"',
            ]);
        }

        return $this->success([
            'status'  => $status, // 'processing' | 'ready' | 'failed' | 'not_found'
        ], 'Report status retrieved');
    }

    /**
     * Generate HTML for readiness report
     */
    private function generateReadinessReportHtml($data, $program = null, $innerOnly = false)
    {
        $company = $data['company_info'];
        $latest = $data['latest_assessment'] ?? null;
        $history = $data['assessment_history'] ?? [];
        $programs = $data['programs'] ?? [];

        $programBanner = $program
            ? "<div style='background:#ecfdf5;border:1px solid #a7f3d0;border-radius:8px;padding:12px 20px;margin-bottom:24px;font-size:14px;color:#065f46;'>
               <strong>📂 Program Scope:</strong> {$program->name} &nbsp;|&nbsp; Template: " . ($program->template?->name ?? 'N/A') . "</div>"
            : '';

        // Build pillar rows with visual bars
        $pillarRows = '';
        if ($latest && isset($latest['pillar_scores'])) {
            foreach ($latest['pillar_scores'] as $pillar) {
                $pct = min(100, $pillar['percentage'] ?? 0);
                if ($pct >= 70) { $barColor = '#10b981'; $label = 'Good'; $labelColor = '#065f46'; $bgColor = '#ecfdf5'; }
                elseif ($pct >= 45) { $barColor = '#f59e0b'; $label = 'Needs Work'; $labelColor = '#78350f'; $bgColor = '#fffbeb'; }
                else { $barColor = '#ef4444'; $label = '⚠ Weak'; $labelColor = '#7f1d1d'; $bgColor = '#fef2f2'; }

                $pillarRows .= "
                    <tr style='background:{$bgColor};'>
                        <td style='padding:12px 16px;font-weight:600;color:#111827;'>{$pillar['pillar_name']}</td>
                        <td style='padding:12px 16px;text-align:center;'>{$pillar['score']} / {$pillar['max_score']}</td>
                        <td style='padding:12px 16px;min-width:200px;'>
                            <div style='background:#e5e7eb;border-radius:999px;height:10px;overflow:hidden;'>
                                <div style='width:{$pct}%;background:{$barColor};height:100%;border-radius:999px;'></div>
                            </div>
                            <span style='font-size:11px;color:#6b7280;margin-top:2px;display:inline-block;'>{$pct}%</span>
                        </td>
                        <td style='padding:12px 16px;font-weight:700;color:{$labelColor};font-size:12px;'>{$label}</td>
                        <td style='padding:12px 16px;text-align:center;color:#6b7280;font-size:12px;'>{$pillar['weight']}%</td>
                    </tr>
                ";
            }
        }

        $historyRows = '';
        foreach ($history as $h) {
            $score = $h['total_score'] ?? 'N/A';
            $historyRows .= "
                <tr>
                    <td style='padding:10px 16px;'>{$h['template_name']}</td>
                    <td style='padding:10px 16px;font-weight:700;'>{$score}</td>
                    <td style='padding:10px 16px;'>{$h['risk_level']}</td>
                    <td style='padding:10px 16px;color:#6b7280;'>{$h['completed_at']}</td>
                </tr>
            ";
        }

        $programsList = '';
        foreach ($programs as $p) {
            $programsList .= "<li style='padding:6px 0;'><strong>{$p['program_name']}</strong> &mdash; {$p['status']} &nbsp; Enrolled: {$p['enrolled_at']}</li>";
        }

        $score = isset($latest['score']) ? round($latest['score'], 1) : 'N/A';
        $riskLevel = $latest['risk_level'] ?? 'Not Assessed';
        $completedAt = $latest['completed_at'] ?? 'N/A';
        $generatedAt = now()->format('Y-m-d H:i');

        // Determine score card color
        $scoreColor = is_numeric($score) ? ($score >= 70 ? '#059669' : ($score >= 45 ? '#d97706' : '#dc2626')) : '#6b7280';

        // Build Recommended Actions
        $actionsHtml = '';
        if ($latest && !empty($latest['recommended_actions'])) {
            $actionsHtml .= "<h2 style='margin-top:16px;' class='avoid-break'>💡 Recommended Actions to Improve</h2><div class='avoid-break'>";
            foreach ($latest['recommended_actions'] as $action) {
                $pColor = $action['priority'] === 'high' ? '#ef4444' : ($action['priority'] === 'medium' ? '#f59e0b' : '#3b82f6');
                $actionsHtml .= "
                <div style='background:#f9fafb; border-left: 4px solid {$pColor}; padding: 10px 14px; margin-bottom: 8px; border-radius: 4px; page-break-inside: avoid;'>
                    <div style='font-weight:700; font-size:12px; color:#111827; margin-bottom:2px;'>{$action['title']}</div>
                    <div style='font-size:11px; color:#4b5563; line-height:1.4;'>{$action['description']}</div>
                    <div style='font-size:10px; color:#6b7280; margin-top:4px; text-transform:uppercase; font-weight:600;'>Pillar: {$action['pillar']} &nbsp;|&nbsp; Impact: +{$action['points']} pts</div>
                </div>";
            }
            $actionsHtml .= "</div>";
        }

        $innerHtml = "
    <div class='page'>
        <div class='cover'>
            <div style='font-size:10px;opacity:.7;margin-bottom:8px;text-transform:uppercase;letter-spacing:.08em;'>Investment Readiness Platform</div>
            <h1>Investment Readiness Report</h1>
            <p style='font-size:16px;font-weight:700;margin-top:4px;'>{$company['company_name']}</p>
            <p>{$company['industry']} &nbsp;|&nbsp; {$company['email']}</p>
            <div class='meta'>Generated: {$generatedAt}</div>
        </div>
        <div class='body'>
            {$programBanner}
            
            <div class='avoid-break'>
                <div class='info-grid'>
                    <div class='info-item'><div class='info-label'>Registration No.</div><div class='info-value'>{$company['registration_number']}</div></div>
                    <div class='info-item'><div class='info-label'>Contact</div><div class='info-value'>{$company['contact_person']}</div></div>
                </div>
            </div>

            <div class='score-card avoid-break'>
                <div style='font-size:12px;opacity:.85;'>Overall Readiness Score</div>
                <div class='score-num'>{$score}</div>
                <div class='score-label'>Risk Level: <strong>{$riskLevel}</strong> &nbsp;|&nbsp; Assessed: {$completedAt}</div>
            </div>

            <div class='avoid-break'>
                <h2>📊 Pillar Breakdown</h2>
                <table>
                    <thead><tr>
                        <th>Pillar</th>
                        <th style='text-align:center;'>Score / Max</th>
                        <th>Progress</th>
                        <th>Status</th>
                        <th style='text-align:center;'>Wt</th>
                    </tr></thead>
                    <tbody>{$pillarRows}</tbody>
                </table>
            </div>

            {$actionsHtml}

            <div class='avoid-break'>
                <h2>📋 Assessment History</h2>
                <table>
                    <thead><tr><th>Template</th><th>Score</th><th>Risk Level</th><th>Date</th></tr></thead>
                    <tbody>{$historyRows}</tbody>
                </table>
            </div>
            
        </div>
        <div class='footer'>
            Generated by IRIP Investment Readiness Platform &nbsp;|&nbsp; Confidential
        </div>
    </div>";

        if ($innerOnly) return $innerHtml;

        return "<!DOCTYPE html>
<html>
<head>
    <title>Investment Readiness Report — {$company['company_name']}</title>
    <meta charset='UTF-8'>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', Arial, sans-serif; background: #f3f4f6; color: #111827; }
        .page { width: 21cm; min-height: 29.7cm; margin: 0 auto; background: white; overflow: hidden; position: relative; }
        .cover { background: linear-gradient(135deg,#064e3b 0%,#065f46 60%,#047857 100%); color:white; padding: 28px 36px 24px; }
        .cover h1 { font-size: 22px; font-weight: 800; margin-bottom: 2px; }
        .cover p { font-size: 13px; opacity: .80; margin-top: 4px; }
        .cover .meta { margin-top: 16px; font-size: 11px; opacity:.70; }
        .body { padding: 24px 36px; }
        .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin: 12px 0; }
        .info-item { background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 6px; padding: 10px 14px; }
        .info-label { font-size: 10px; color: #6b7280; text-transform: uppercase; letter-spacing: .05em; }
        .info-value { font-size: 13px; font-weight: 600; color: #111827; margin-top: 2px; }
        .score-card { text-align: center; background: {$scoreColor}; color: white; border-radius: 8px; padding: 20px; margin: 16px 0; }
        .score-num { font-size: 42px; font-weight: 800; line-height: 1; }
        .score-label { font-size: 12px; opacity: .85; margin-top: 4px; }
        h2 { font-size: 14px; font-weight: 700; color: #064e3b; margin: 20px 0 8px; border-bottom: 2px solid #d1fae5; padding-bottom: 4px; }
        table { width: 100%; border-collapse: collapse; font-size: 11px; margin-bottom: 12px; }
        thead tr { background: #f9fafb; border-bottom: 2px solid #e5e7eb; }
        th { padding: 6px 12px; text-align: left; font-size: 10px; text-transform: uppercase; color: #6b7280; font-weight: 600; letter-spacing:.04em; }
        td { padding: 6px 12px; }
        tbody tr { border-bottom: 1px solid #f3f4f6; page-break-inside: avoid; }
        .footer { text-align: center; font-size: 10px; color: #9ca3af; padding: 16px 36px 20px; }
        .avoid-break { page-break-inside: avoid; }
        @media print { 
            body { background: white; margin: 0; padding: 0; } 
            .page { box-shadow: none; margin: 0; width: 100%; height: 100%; } 
            * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; color-adjust: exact !important; }
        }
    </style>
</head>
<body>
    {$innerHtml}
</body>
</html>";
    }

    /**
     * Generate HTML for portfolio report (Batch individual SME reports)
     */
    private function generatePortfolioReportHtml($allSmeData, $program = null)
    {
        if (empty($allSmeData)) {
            return "<html><body><h2>No SMEs assessed yet in this program.</h2></body></html>";
        }

        $pagesHtml = '';
        foreach ($allSmeData as $index => $data) {
            $pagesHtml .= $this->generateReadinessReportHtml($data, $program, true);
        }

        return "<!DOCTYPE html>
<html>
<head>
    <title>Batch Readiness Reports</title>
    <meta charset='UTF-8'>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', Arial, sans-serif; background: #e5e7eb; color: #111827; padding: 20px 0; }
        .page { width: 21cm; min-height: 29.7cm; margin: 0 auto 30px auto; background: white; overflow: hidden; position: relative; box-shadow: 0 4px 24px rgba(0,0,0,.15); }
        .page-break { page-break-after: always; display: none; }
        
        .cover { background: linear-gradient(135deg,#064e3b 0%,#065f46 60%,#047857 100%); color:white; padding: 28px 36px 24px; }
        .cover h1 { font-size: 22px; font-weight: 800; margin-bottom: 2px; }
        .cover p { font-size: 13px; opacity: .80; margin-top: 4px; }
        .cover .meta { margin-top: 16px; font-size: 11px; opacity:.70; }
        .body { padding: 24px 36px; }
        .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin: 12px 0; }
        .info-item { background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 6px; padding: 10px 14px; }
        .info-label { font-size: 10px; color: #6b7280; text-transform: uppercase; letter-spacing: .05em; }
        .info-value { font-size: 13px; font-weight: 600; color: #111827; margin-top: 2px; }
        .score-card { text-align: center; color: white; border-radius: 8px; padding: 20px; margin: 16px 0; }
        .score-num { font-size: 42px; font-weight: 800; line-height: 1; }
        .score-label { font-size: 12px; opacity: .85; margin-top: 4px; }
        h2 { font-size: 14px; font-weight: 700; color: #064e3b; margin: 20px 0 8px; border-bottom: 2px solid #d1fae5; padding-bottom: 4px; }
        table { width: 100%; border-collapse: collapse; font-size: 11px; margin-bottom: 12px; }
        thead tr { background: #f9fafb; border-bottom: 2px solid #e5e7eb; }
        th { padding: 6px 12px; text-align: left; font-size: 10px; text-transform: uppercase; color: #6b7280; font-weight: 600; letter-spacing:.04em; }
        td { padding: 6px 12px; }
        tbody tr { border-bottom: 1px solid #f3f4f6; page-break-inside: avoid; }
        .footer { text-align: center; font-size: 10px; color: #9ca3af; padding: 16px 36px 20px; }
        .avoid-break { page-break-inside: avoid; }
        @media print { 
            body { background: white; margin: 0; padding: 0; } 
            .page { box-shadow: none; margin: 0; width: 100%; height: 100%; min-height: auto; page-break-after: always; }
            .page:last-child { page-break-after: auto; }
            * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; color-adjust: exact !important; }
        }
    </style>
</head>
<body>
    {$pagesHtml}
</body>
</html>";
    }

    /**
     * Calculate pillar scores from assessment
     */
    private function calculatePillarScores($assessment)
    {
        $pillars = Pillar::all();
        $pillarScores = [];

        // First try from assessment_responses (more accurate)
        $responses = \App\Models\AssessmentResponse::where('assessment_id', $assessment->id)
            ->with('question')
            ->get();

        if ($responses->isNotEmpty()) {
            $grouped = [];
            foreach ($responses as $r) {
                if (!$r->question) continue;
                $pid = $r->question->pillar_id;
                if (!isset($grouped[$pid])) $grouped[$pid] = ['earned' => 0, 'max' => 0];
                $grouped[$pid]['earned'] += (float)$r->score_awarded;
                $grouped[$pid]['max'] += (float)$r->question->weight;
            }
            foreach ($pillars as $pillar) {
                $d = $grouped[$pillar->id] ?? ['earned' => 0, 'max' => 0];
                if ($d['max'] <= 0) continue;
                $pillarScores[] = [
                    'pillar_id'   => $pillar->id,
                    'pillar_name' => $pillar->name,
                    'score'       => round($d['earned'], 2),
                    'max_score'   => $d['max'],
                    'percentage'  => round(($d['earned'] / $d['max']) * 100, 1),
                    'weight'      => $pillar->weight,
                ];
            }
            return $pillarScores;
        }

        // Fallback: questions_snapshot
        $snapshot = $assessment->questions_snapshot ?? [];
        foreach ($pillars as $pillar) {
            $pillarQuestions = collect($snapshot)->where('pillar_id', $pillar->id);
            if ($pillarQuestions->count() > 0) {
                $score    = $pillarQuestions->avg('score_awarded');
                $maxScore = $pillarQuestions->sum('weight');
                $pillarScores[] = [
                    'pillar_id'   => $pillar->id,
                    'pillar_name' => $pillar->name,
                    'score'       => round($score, 2),
                    'max_score'   => $maxScore,
                    'percentage'  => $maxScore > 0 ? round(($score / $maxScore) * 100, 1) : 0,
                    'weight'      => $pillar->weight,
                ];
            }
        }
        return $pillarScores;
    }

    /**
     * Generate detailed SME report data (optionally scoped to a program)
     */
    private function generateSmeReportData($sme, $program = null)
    {
        // If scoped to a program, use that program's template_id
        if ($program && $program->template_id) {
            $latestAssessment = Assessment::where('sme_id', $sme->id)
                ->where('template_id', $program->template_id)
                ->where('status', 'Completed')
                ->latest()
                ->first();
        } else {
            $latestAssessment = $sme->assessments()
                ->where('status', 'Completed')
                ->latest()
                ->first();
        }

        $pillarScores = $latestAssessment ? $this->calculatePillarScores($latestAssessment) : [];

        // Assessment history
        $assessmentHistory = $sme->assessments()
            ->where('status', 'Completed')
            ->with('template')
            ->orderBy('completed_at', 'desc')
            ->get()
            ->map(function ($assessment) {
                return [
                    'assessment_id' => $assessment->id,
                    'template_name' => $assessment->template?->name,
                    'total_score'   => round($assessment->total_score, 1),
                    'risk_level'    => $assessment->risk_level,
                    'completed_at'  => $assessment->completed_at?->format('Y-m-d')
                ];
            });

        // Programs enrolled
        $programs = ProgramEnrollment::where('sme_id', $sme->id)
            ->with('program')
            ->get()
            ->map(function ($enrollment) {
                return [
                    'program_name' => $enrollment->program?->name,
                    'status'       => $enrollment->status,
                    'enrolled_at'  => $enrollment->enrollment_date?->format('Y-m-d')
                ];
            });

        return [
            'company_info' => [
                'company_name'        => $sme->company_name,
                'registration_number' => $sme->registration_number ?? '—',
                'industry'            => $sme->industry ?? '—',
                'years_in_operation'  => $sme->years_in_operation ?? '—',
                'total_employees'     => $sme->total_employees ?? '—',
                'contact_person'      => $sme->user?->full_name ?? '—',
                'email'               => $sme->user?->email ?? '—',
                'phone'               => $sme->user?->phone ?? '—',
            ],
            'latest_assessment' => $latestAssessment ? [
                'score'       => round($latestAssessment->total_score, 1),
                'risk_level'  => $latestAssessment->risk_level ?? $this->getRiskLabel($latestAssessment->total_score),
                'completed_at' => $latestAssessment->completed_at?->format('Y-m-d'),
                'pillar_scores' => $pillarScores,
                'recommended_actions' => $this->calculateTopActions($latestAssessment, 3)
            ] : null,
            'assessment_history' => $assessmentHistory,
            'programs'           => $programs,
        ];
    }



    /**
     * Generate detailed Program report data
     */
    private function generateProgramReportData($program)
    {
        $enrollments = ProgramEnrollment::where('program_id', $program->id)
            ->whereNotNull('sme_id')
            ->with('smeProfile.user')
            ->get();

        $smeData = [];
        $totalScore = 0;
        $completedCount = 0;

        foreach ($enrollments as $enrollment) {
            $sme = $enrollment->smeProfile;
            if (!$sme) continue;

            $assessment = Assessment::where('sme_id', $sme->id)
                ->where('template_id', $program->template_id)
                ->where('status', 'Completed')
                ->latest()
                ->first();

            $pillarScores = $assessment ? $this->calculatePillarScores($assessment) : [];

            if ($assessment) {
                $totalScore += $assessment->total_score;
                $completedCount++;
            }

            $smeData[] = [
                'sme_id' => $sme->id,
                'company_name' => $sme->company_name,
                'contact_name' => $sme->user?->full_name,
                'email' => $sme->user?->email,
                'industry' => $sme->industry,
                'enrollment_status' => $enrollment->status,
                'enrolled_at' => $enrollment->enrollment_date?->format('Y-m-d'),
                'assessment_score' => $assessment?->total_score,
                'risk_level' => $assessment?->risk_level,
                'completed_at' => $assessment?->completed_at?->format('Y-m-d'),
                'pillar_scores' => $pillarScores
            ];
        }

        return [
            'program_info' => [
                'name' => $program->name,
                'description' => $program->description,
                'status' => $program->status,
                'sector' => $program->sector,
                'template_name' => $program->template?->name,
                'duration' => $program->duration,
                'investment_amount' => $program->investment_amount
            ],
            'summary' => [
                'total_smes' => $enrollments->count(),
                'completed_assessments' => $completedCount,
                'avg_score' => $completedCount > 0 ? round($totalScore / $completedCount, 2) : 0,
                'completion_rate' => $enrollments->count() > 0 ? round(($completedCount / $enrollments->count()) * 100, 1) : 0
            ],
            'sme_details' => $smeData
        ];
    }
}