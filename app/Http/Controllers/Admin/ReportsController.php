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
use App\Services\AssessmentService;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

class ReportsController extends Controller
{
    protected $assessmentService;

    public function __construct(AssessmentService $assessmentService)
    {
        $this->assessmentService = $assessmentService;
    }

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
                $enrollments = $program->enrollments;
                $totalSmes = $enrollments->count();
                $completedAssessments = 0;
                $totalScore = 0;
                $smeScores = [];

                foreach ($enrollments as $enrollment) {
                    $smeProfile = $enrollment->smeProfile;
                    if (!$smeProfile)
                        continue;

                    // Already eager-loaded — no extra query
                    $assessment = $smeProfile->assessments
                        ->where('template_id', $program->template_id)
                        ->where('status', 'Completed')
                        ->sortByDesc('created_at')
                        ->first();

                        $completedAssessments++;
                        $totalScore += $assessment->total_score;
                        $thresholds = $this->assessmentService->getThresholds($program->id);
                        $pillarScores = $this->assessmentService->calculatePillarScores($assessment, $thresholds);
                        $smeScores[] = [
                            'sme_id' => $smeProfile->id,
                            'sme_name' => $smeProfile->company_name,
                            'user_name' => $smeProfile->user?->full_name,
                            'email' => $smeProfile->user?->email,
                            'industry' => $smeProfile->industry,
                            'assessment_id' => $assessment->id,
                            'total_score' => $assessment->total_score,
                            'risk_level' => $this->assessmentService->getThresholdLabel($assessment->total_score, $thresholds),
                            'completed_at' => $assessment->completed_at?->format('Y-m-d'),
                            'pillar_scores' => $pillarScores,
                        ];
                }

                return [
                    'id' => $program->id,
                    'name' => $program->name,
                    'description' => $program->description,
                    'status' => $program->status,
                    'sector' => $program->sector,
                    'template_name' => $program->template?->name,
                    'total_smes' => $totalSmes,
                    'completed_assessments' => $completedAssessments,
                    'avg_score' => $completedAssessments > 0 ? round($totalScore / $completedAssessments, 2) : 0,
                    'sme_scores' => $smeScores,
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
        $programId = $request->input('program_id');
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
                $program = $enrollment->program;
                // Already in $sme->assessments — filter in PHP
                $assessment = $sme->assessments
                    ->where('template_id', $program?->template_id)
                    ->where('status', 'Completed')
                    ->sortByDesc('created_at')
                    ->first();

                return [
                    'program_id' => $program?->id,
                    'program_name' => $program?->name,
                    'enrollment_status' => $enrollment->status,
                    'enrollment_date' => $enrollment->enrollment_date?->format('Y-m-d'),
                    'assessment_score' => $assessment?->total_score,
                    'assessment_status' => $assessment?->status,
                    'completed_at' => $assessment?->completed_at?->format('Y-m-d'),
                ];
            });

            $thresholds = $this->assessmentService->getThresholds($programId);
            $pillarScores = $latestAssessment ? $this->assessmentService->calculatePillarScores($latestAssessment, $thresholds) : [];

            return [
                'id' => $sme->id,
                'company_name' => $sme->company_name,
                'user_name' => $sme->user?->full_name,
                'email' => $sme->user?->email,
                'phone' => $sme->user?->phone,
                'industry' => $sme->industry,
                'registration_number' => $sme->registration_number,
                'years_in_operation' => $sme->years_in_operation,
                'total_employees' => $sme->total_employees,
                'latest_score' => $latestAssessment ? round((float) $latestAssessment->total_score, 1) : null,
                'latest_risk_level' => $latestAssessment
                    ? $this->assessmentService->getThresholdLabel($latestAssessment->total_score, $thresholds)
                    : ($targetTemplateId ? 'Not Assessed' : null),
                'last_assessed' => $latestAssessment?->completed_at?->format('Y-m-d'),
                'programs' => $programs,
                'pillar_scores' => $pillarScores,
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
        $programId = $request->input('program_id');
        $targetTemplateId = $programId ? Program::find($programId)?->template_id : null;
        $cacheKey = 'report_scores_' . ($programId ?? 'all');

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
                    'total_assessments' => 0,
                    'avg_score' => 0,
                    'score_distribution' => [],
                    'by_program' => [],
                    'by_pillar' => [],
                ], 'No completed assessments found');
            }

            $avgScore = round($assessments->avg('total_score'), 2);

            $distribution = [
                'excellent' => $assessments->where('total_score', '>=', 80)->count(),
                'good' => $assessments->whereBetween('total_score', [60, 79.99])->count(),
                'average' => $assessments->whereBetween('total_score', [40, 59.99])->count(),
                'needs_improvement' => $assessments->where('total_score', '<', 40)->count(),
            ];

            // ✅ FIX: Pre-load all enrollments grouped by program_id BEFORE the map loop.
            // Without this, every program iteration fires its own ProgramEnrollment query.
            $allEnrollmentsByProgram = ProgramEnrollment::whereNotNull('sme_id')
                ->pluck('sme_id', 'program_id')
                ->groupBy(function ($smeId, $programId) {
                    return $programId;
                });

            // Reload as a full grouped collection (pluck only gives one value per key)
            $enrollmentsByProgram = ProgramEnrollment::whereNotNull('sme_id')
                ->get(['program_id', 'sme_id'])
                ->groupBy('program_id');

            $byProgram = Program::with(['template'])->get()->map(function ($program) use ($enrollmentsByProgram) {
                // ✅ Use pre-loaded enrollments — no extra DB query per program
                $smeIds = ($enrollmentsByProgram->get($program->id) ?? collect())->pluck('sme_id');
                $scores = Assessment::whereIn('sme_id', $smeIds)
                    ->where('template_id', $program->template_id)
                    ->where('status', 'Completed')
                    ->pluck('total_score');
                return [
                    'program_id' => $program->id,
                    'program_name' => $program->name,
                    'total_assessments' => $scores->count(),
                    'avg_score' => $scores->count() > 0 ? round($scores->avg(), 2) : 0,
                    'min_score' => $scores->count() > 0 ? round($scores->min(), 2) : 0,
                    'max_score' => $scores->count() > 0 ? round($scores->max(), 2) : 0,
                ];
            });

            $pillars = Pillar::all();
            $byPillar = [];
            foreach ($pillars as $pillar) {
                $pillarScores = [];
                foreach ($assessments as $assessment) {
                    $snapshot = $assessment->questions_snapshot ?? [];
                    $pillarQuestions = collect($snapshot)->where('pillar_id', $pillar->id);
                    if ($pillarQuestions->count() > 0) {
                        $pillarScores[] = $pillarQuestions->avg('score_awarded');
                    }
                }
                $byPillar[] = [
                    'pillar_id' => $pillar->id,
                    'pillar_name' => $pillar->name,
                    'avg_score' => count($pillarScores) > 0 ? round(array_sum($pillarScores) / count($pillarScores), 2) : 0,
                    'weight' => $pillar->weight,
                ];
            }

            return [
                'total_assessments' => $totalAssessments,
                'avg_score' => $avgScore,
                'score_distribution' => $distribution,
                'by_program' => $byProgram,
                'by_pillar' => $byPillar,
            ];
        }); // end Cache::remember

        return $this->success($cached, 'Scores report retrieved successfully');
    }

    /**
     * Export report data (PDF generation placeholder)
     */
    public function export(Request $request)
    {
        $id = $request->input('id'); // SME ID
        $programId = $request->input('programId');

        $user = null;

        if (auth('sanctum')->check()) {
            $user = auth('sanctum')->user();
        }

        if (!$user || !in_array($user->role, ['ADMIN', 'INVESTOR'])) {
            return response()->json(['error' => 'Unauthorized: Access restricted to Admins and Investors.'], 401);
        }

        $smeQuery = \App\Models\SmeProfile::with(['user', 'assessments.template', 'assessments.responses']);

        if ($id) {
            // The admin frontend always passes the sme_profile.id.
            // Using OR (id=X OR user_id=X) can accidentally match two DIFFERENT profiles.
            // Strict id lookup is correct.
            $smeQuery->where('id', $id);
        }

        if ($programId) {
            $smeIds = \App\Models\ProgramEnrollment::where('program_id', $programId)
                ->whereNotNull('sme_id')
                ->pluck('sme_id');
            $smeQuery->whereIn('id', $smeIds);
        }

        $smes = $smeQuery->get();

        if ($smes->isEmpty()) {
            return response()->json(['error' => 'No completed assessments found for this filter.'], 404);
        }

        $filename = "sme_assessment_export_" . date('Y_m_d_His') . ".xlsx";
        if ($id && $smes->count() === 1) {
            $compName = Str::slug($smes->first()->company_name ?? 'sme_report');
            $filename = "{$compName}_assessment.xlsx";
        } else if ($programId) {
            $program = \App\Models\Program::find($programId);
            $pName = Str::slug($program->name ?? 'program');
            $filename = "{$pName}_raw_scores.xlsx";
        }

        $headers = [
            "Content-type" => "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
            "Content-Disposition" => "attachment; filename=$filename",
            "Pragma" => "no-cache",
            "Cache-Control" => "must-revalidate, post-check=0, pre-check=0",
            "Expires" => "0"
        ];

        $pillars = \Illuminate\Support\Facades\Cache::remember('pillars_pluck', 3600, fn() => \App\Models\Pillar::pluck('name', 'id'));

        // ✅ FIX: Pre-load all enrollments keyed by sme_id BEFORE the loop.
        // This replaces 2,000 individual queries with a SINGLE query.
        $allEnrollments = \App\Models\ProgramEnrollment::with('program')
            ->whereIn('sme_id', $smes->pluck('id'))
            ->get()
            ->groupBy('sme_id');

        // ✅ FIX: Load program once outside the loop if programId is set.
        $programForExport = $programId ? \App\Models\Program::find($programId) : null;

        $callback = function () use (
            $smes, $programId, $pillars, $allEnrollments, $programForExport
        ) {
            // ── Colour palette ────────────────────────────────────────────────
            $C_DARK   = '05624E';
            $C_MID    = '0A8C6E';
            $C_LIGHT  = 'E8F5F1';
            $C_WHITE  = 'FFFFFF';
            $C_GREY   = 'F8FAFC';
            $C_BORDER = 'CBD5E1';
            $C_TEXT   = '0F172A';
            $C_MUTED  = '64748B';

            $hdrStyle = fn(string $bg) => [
                'font'      => ['name' => 'Arial', 'size' => 10, 'bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $bg]],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER,
                                'vertical'   => Alignment::VERTICAL_CENTER, 'wrapText' => true],
                'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => $C_BORDER]]],
            ];

            $cellStyle = fn(string $bg, string $color = '0F172A', bool $bold = false, string $halign = Alignment::HORIZONTAL_LEFT) => [
                'font'      => ['name' => 'Arial', 'size' => 10, 'bold' => $bold, 'color' => ['rgb' => $color]],
                'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $bg]],
                'alignment' => ['horizontal' => $halign, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
                'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => $C_BORDER]]],
            ];

            $pillarPct   = fn(float $a, float $m): float => $m > 0 ? round($a / $m * 100, 1) : 0;
            $pillarColor = fn(float $p): string => $p >= 70 ? '10B981' : ($p >= 45 ? 'F59E0B' : 'EF4444');
            $scoreColor  = fn(float $a, float $m): string => $a === $m ? '10B981' : ($a == 0 ? 'EF4444' : 'F59E0B');

            $spreadsheet = new Spreadsheet();
            $spreadsheet->getProperties()->setTitle('SME Assessment Report')->setCreator('IRIP Platform');

            $hasData    = false;
            $sheetIndex = 0;

            foreach ($smes as $sme) {
                // ── Resolve assessment ────────────────────────────────────────
                $assessment        = null;
                $activeProgramName = 'N/A';

                if ($programId) {
                    if ($programForExport) {
                        $activeProgramName = $programForExport->name;
                        $assessment = $sme->assessments->where('template_id', $programForExport->template_id)
                            ->where('status', 'Completed')->sortByDesc('completed_at')->first();
                    }
                } else {
                    $assessment = $sme->assessments->where('status', 'Completed')->sortByDesc('completed_at')->first();
                    if ($assessment) {
                        $smeEnrollments = $allEnrollments->get($sme->id, collect());
                        $enrollment = $smeEnrollments->first(fn($e) => $e->program && $e->program->template_id === $assessment->template_id);
                        if ($enrollment?->program) $activeProgramName = $enrollment->program->name;
                    }
                }

                if (!$assessment) continue;

                $snapshot = $assessment->questions_snapshot ?? [];
                if (!is_array($snapshot)) $snapshot = json_decode($snapshot, true) ?? [];

                $t           = $this->assessmentService->getThresholds($assessment->program_id);
                $risk        = $this->assessmentService->getThresholdLabel($assessment->total_score, $t);
                $assDate     = $assessment->completed_at?->format('Y-m-d H:i') ?? 'N/A';
                $totalScore  = (float)($assessment->total_score ?? 0);
                $templateName = $assessment->template->name ?? 'N/A';
                $companyName  = $sme->company_name ?? 'N/A';
                $ownerName    = $sme->user?->full_name ?? 'N/A';
                $hasData      = true;

                $responses = collect($assessment->responses)->keyBy('question_id');

                $resolveAnswer = function ($resp, string $qType): string {
                    if (!$resp || is_null($resp->answer_value)) return 'No Answer';
                    $raw     = $resp->answer_value;
                    $decoded = is_string($raw) ? (json_decode($raw, true) ?? $raw) : $raw;
                    if (is_bool($decoded)) return $decoded ? 'Yes' : 'No';
                    if (is_array($decoded)) {
                        if (isset($decoded['label'])) return (string)$decoded['label'];
                        $labels = array_map(fn($item) => is_array($item)
                            ? (string)($item['label'] ?? $item['value'] ?? json_encode($item))
                            : (string)$item, $decoded);
                        return implode('; ', $labels);
                    }
                    if (is_numeric($decoded)) return $qType === 'Yes/No' ? ((int)$decoded === 1 ? 'Yes' : 'No') : (string)$decoded;
                    if (is_string($decoded)) return $qType === 'Yes/No'
                        ? (in_array(strtolower($decoded), ['1','true','yes']) ? 'Yes' : 'No')
                        : ($decoded ?: 'No Answer');
                    return (string)$decoded;
                };

                // ── Build pillar aggregates ───────────────────────────────────
                $pillarAgg = [];
                foreach ($snapshot as $q) {
                    $pId   = $q['pillar_id'] ?? null;
                    $pName = ($pId && $pillars->has($pId)) ? $pillars->get($pId) : 'Unknown';
                    if (!isset($pillarAgg[$pName])) $pillarAgg[$pName] = ['awarded' => 0.0, 'max' => 0.0, 'questions' => []];
                    $qId     = $q['id'] ?? null;
                    $resp    = $qId ? $responses->get($qId) : null;
                    $awarded = (float)($resp->score_awarded ?? 0);
                    $max     = (float)($q['weight'] ?? $q['max_score'] ?? 0);
                    $pillarAgg[$pName]['awarded']     += $awarded;
                    $pillarAgg[$pName]['max']         += $max;
                    $pillarAgg[$pName]['questions'][] = [
                        'title'   => $q['text'] ?? $q['title'] ?? 'Unknown Question',
                        'answer'  => $resolveAnswer($resp, $q['type'] ?? ''),
                        'awarded' => $awarded,
                        'max'     => $max,
                    ];
                }

                $safeTitle = mb_substr(preg_replace('/[\/\\\?\*\[\]:]/', '', $companyName), 0, 26);

                // ════════════════════════════════════════════════════════════════
                //  SHEET 1 — SUMMARY
                // ════════════════════════════════════════════════════════════════
                $ws1 = $sheetIndex === 0 ? $spreadsheet->getActiveSheet() : $spreadsheet->createSheet();
                $ws1->setTitle($safeTitle . ' - Sum');

                $ws1->mergeCells('A1:G1');
                $ws1->setCellValue('A1', 'SME Assessment Report');
                $ws1->getStyle('A1')->applyFromArray([
                    'font'      => ['name' => 'Arial', 'size' => 14, 'bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                    'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $C_DARK]],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER, 'indent' => 1],
                ]);
                $ws1->getRowDimension(1)->setRowHeight(36);

                $infoRows = [
                    ['Company',     $companyName],   ['Owner',    $ownerName],
                    ['Reg No',      $sme->registration_number ?? 'N/A'],
                    ['Industry',    $sme->industry ?? 'N/A'],
                    ['Program',     $activeProgramName], ['Template', $templateName],
                    ['Date',        $assDate],        ['Risk',     $risk],
                    ['Total Score', $totalScore],
                ];
                $r = 3;
                foreach ($infoRows as [$lbl, $val]) {
                    $ws1->setCellValue("A{$r}", $lbl);
                    $ws1->setCellValue("B{$r}", $val);
                    $isBold  = $lbl === 'Total Score';
                    $vColor  = $lbl === 'Total Score' ? $C_DARK : $C_TEXT;
                    $ws1->getStyle("A{$r}")->applyFromArray($cellStyle($C_GREY, $C_MUTED, true));
                    $ws1->getStyle("B{$r}")->applyFromArray($cellStyle($C_WHITE, $vColor, $isBold));
                    $ws1->getRowDimension($r)->setRowHeight(22);
                    $r++;
                }

                $r++;
                $ws1->mergeCells("A{$r}:F{$r}");
                $ws1->setCellValue("A{$r}", 'Pillar Score Breakdown');
                $ws1->getStyle("A{$r}")->applyFromArray($hdrStyle($C_MID));
                $ws1->getRowDimension($r)->setRowHeight(22);
                $r++;

                foreach (['Pillar', 'Questions', 'Awarded', 'Max', '% Achieved', 'Weight'] as $ci => $h) {
                    $col = chr(65 + $ci);
                    $ws1->setCellValue("{$col}{$r}", $h);
                    $ws1->getStyle("{$col}{$r}")->applyFromArray($hdrStyle($C_DARK));
                }
                $ws1->getRowDimension($r)->setRowHeight(22);
                $r++;

                $alt = false;
                foreach ($pillarAgg as $pName => $pd) {
                    $pct    = $pillarPct($pd['awarded'], $pd['max']);
                    $pColor = $pillarColor($pct);
                    $rowBg  = $alt ? $C_GREY : 'F0FDF4';
                    $alt    = !$alt;
                    $fwW    = array_sum(array_column($pd['questions'], 'max'));
                    $vals   = [$pName, count($pd['questions']), round($pd['awarded'], 2), round($pd['max'], 2), $pct . '%', $fwW];
                    foreach ($vals as $ci => $v) {
                        $col  = chr(65 + $ci);
                        $isP  = $ci === 4;
                        $ws1->setCellValue("{$col}{$r}", $v);
                        $ws1->getStyle("{$col}{$r}")->applyFromArray($cellStyle(
                            $rowBg, $isP ? $pColor : $C_TEXT, $isP,
                            $ci === 0 ? Alignment::HORIZONTAL_LEFT : Alignment::HORIZONTAL_CENTER
                        ));
                    }
                    $ws1->getRowDimension($r)->setRowHeight(20);
                    $r++;
                }

                foreach (['A' => 22, 'B' => 36, 'C' => 14, 'D' => 12, 'E' => 14, 'F' => 14, 'G' => 12] as $col => $w)
                    $ws1->getColumnDimension($col)->setWidth($w);
                $ws1->freezePane('A4');

                // ════════════════════════════════════════════════════════════════
                //  SHEET 2 — QUESTION DETAIL
                // ════════════════════════════════════════════════════════════════
                $ws2 = $spreadsheet->createSheet();
                $ws2->setTitle($safeTitle . ' - Qs');

                $ws2->mergeCells('A1:F1');
                $ws2->setCellValue('A1', "Questions — {$companyName} ({$assDate})");
                $ws2->getStyle('A1')->applyFromArray([
                    'font'      => ['name' => 'Arial', 'size' => 13, 'bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                    'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $C_DARK]],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER, 'indent' => 1],
                ]);
                $ws2->getRowDimension(1)->setRowHeight(30);

                foreach (['Pillar', 'Question', 'Answer', 'Awarded', 'Max', 'Weight'] as $ci => $h) {
                    $col = chr(65 + $ci);
                    $ws2->setCellValue("{$col}2", $h);
                    $ws2->getStyle("{$col}2")->applyFromArray($hdrStyle($C_DARK));
                }
                $ws2->getRowDimension(2)->setRowHeight(22);

                $qRow = 3;
                foreach ($pillarAgg as $pName => $pd) {
                    $pct    = $pillarPct($pd['awarded'], $pd['max']);
                    $ws2->mergeCells("A{$qRow}:F{$qRow}");
                    $ws2->setCellValue("A{$qRow}", "  {$pName}   |   {$pd['awarded']} / {$pd['max']}   ({$pct}%)");
                    $ws2->getStyle("A{$qRow}")->applyFromArray([
                        'font'      => ['name' => 'Arial', 'size' => 10, 'bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                        'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $C_MID]],
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER, 'indent' => 1],
                        'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => $C_BORDER]]],
                    ]);
                    $ws2->getRowDimension($qRow)->setRowHeight(22);
                    $qRow++;

                    foreach ($pd['questions'] as $i => $q) {
                        $rowBg  = ($i % 2 === 0) ? $C_LIGHT : $C_WHITE;
                        $sColor = $scoreColor($q['awarded'], $q['max']);
                        $vals   = ['', $q['title'], $q['answer'], $q['awarded'], $q['max'], $q['max']];
                        foreach ($vals as $ci => $v) {
                            $col  = chr(65 + $ci);
                            $isS  = $ci === 3;
                            $isN  = $ci >= 3;
                            $ws2->setCellValue("{$col}{$qRow}", $v);
                            $ws2->getStyle("{$col}{$qRow}")->applyFromArray($cellStyle(
                                $rowBg, $isS ? $sColor : $C_TEXT, $isS,
                                $isN ? Alignment::HORIZONTAL_CENTER : Alignment::HORIZONTAL_LEFT
                            ));
                        }
                        $ws2->getRowDimension($qRow)->setRowHeight(38);
                        $qRow++;
                    }
                    $qRow++;
                }

                foreach (['A' => 2, 'B' => 52, 'C' => 34, 'D' => 14, 'E' => 12, 'F' => 14] as $col => $w)
                    $ws2->getColumnDimension($col)->setWidth($w);
                $ws2->freezePane('B3');

                $sheetIndex += 2;
            }

            if (!$hasData) {
                $spreadsheet->getActiveSheet()->setCellValue('A1', 'No assessments found for this filter combination.');
            }

            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        };

        // Log the export
        \App\Models\AuditLog::create([
            'user_id'       => $user->id ?? null,
            'action'        => 'EXPORTED_DATA',
            'target_entity' => 'SmeProfile',
            'target_id'     => $id ?? 0,
            'details'       => json_encode([
                'report_type' => 'Raw Scores XLSX Export',
                'program_id' => $programId,
                'sme_id' => $id
            ]),
            'ip_address' => $request->ip()
        ]);

        return response()->streamDownload($callback, $filename, $headers);
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
        $smeId = $request->input('smeId');
        $programId = $request->input('programId'); // Optional: scope to a specific program

        // If no smeId, generate portfolio report instead
        if (!$smeId) {
            return $this->portfolio($request);
        }

        if (!auth('sanctum')->check()) {
            return $this->error('Authentication required', 401);
        }

        $user = auth('sanctum')->user();

        if (!in_array($user->role, ['ADMIN', 'INVESTOR'])) {
            return $this->error('Unauthorized', 401);
        }

        // Lookup by id only — the frontend always passes sme_profile.id.
        // Using OR (id=X OR user_id=X) risks matching two different profiles.
        $sme = SmeProfile::with(['user', 'assessments'])->where('id', $smeId)->firstOrFail();
        
        $data = $this->assessmentService->generateSmeReportData($sme, $programId);
        $program = $programId ? Program::with('template')->find($programId) : null;

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
        $programId = $request->input('programId');

        if (!auth('sanctum')->check()) {
            return $this->error('Authentication required', 401);
        }

        $user = auth('sanctum')->user();

        if (!in_array($user->role, ['ADMIN', 'INVESTOR'])) {
            return $this->error('Unauthorized', 401);
        }

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
                'async' => true,
                'report_key' => $reportKey,
                'message' => 'Report is being generated in the background. Poll /api/admin/reports/status?key=' . $reportKey . ' for updates.',
            ], 'Large report queued for background processing');
        }

        // ✅ For small batches: generate inline (fast, no queue needed)
        if ($program) {
            $smeIds = ProgramEnrollment::where('program_id', $program->id)->whereNotNull('sme_id')->pluck('sme_id');
            $allSmes = SmeProfile::with(['user', 'assessments.template', 'enrollments.program'])->whereIn('id', $smeIds)->get();
        } else {
            $allSmes = SmeProfile::with(['user', 'assessments.template', 'enrollments.program'])->get();
        }

        $allSmeData = $allSmes->map(fn($sme) => $this->assessmentService->generateSmeReportData($sme, $programId));
        $html = $this->generatePortfolioReportHtml($allSmeData, $program);

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
            'Content-Type' => 'text/html',
            'Content-Disposition' => 'inline; filename="portfolio-report.html"',
        ]);
    }

    /**
     * GET /api/admin/reports/status?key={key}
     * Poll this endpoint to check if a background batch report is ready.
     */
    public function reportStatus(Request $request)
    {
        $key = $request->input('key');
        $status = Cache::get($key . '_status', 'not_found');

        if ($status === 'ready') {
            // Data is in cache — return it and clean up
            $data = Cache::get($key . '_data', []);
            Cache::forget($key . '_status');
            Cache::forget($key . '_data');

            // Build and return the HTML report
            $programId = $request->input('programId');
            $program = $programId ? Program::with('template')->find($programId) : null;
            $html = $this->generatePortfolioReportHtml(collect($data), $program);

            return response($html, 200, [
                'Content-Type' => 'text/html',
                'Content-Disposition' => 'inline; filename="portfolio-report.html"',
            ]);
        }

        return $this->success([
            'status' => $status, // 'processing' | 'ready' | 'failed' | 'not_found'
        ], 'Report status retrieved');
    }

    /**
     * Get shared CSS for PDF rendering (ensures clean A4 print rules and Brand Green theme)
     */
    private function getSharedPdfCss()
    {
        return "
        * { box-sizing: border-box; margin: 0; padding: 0; }
        @page { margin: 0; size: A4 portrait; }
        body { font-family: 'Inter', system-ui, -apple-system, sans-serif; background: #e2e8f0; color: #1e293b; }
        
        /* Container mimicking A4 paper */
        .page { 
            width: 21cm; 
            min-height: 29.7cm; 
            margin: 2rem auto; 
            background: white; 
            position: relative; 
            box-shadow: 0 10px 30px rgba(0,0,0,0.1); 
            display: flex; 
            flex-direction: column;
        }
        
        .cover { background: #05624e; color: white; padding: 24px 40px; border-bottom: 4px solid #034234; }
        .cover h1 { font-size: 26px; font-weight: 800; margin-bottom: 2px; letter-spacing: -0.01em; }
        .cover p { font-size: 14px; opacity: .90; margin-top: 2px; font-weight: 500; }
        .cover .meta { margin-top: 16px; font-size: 11px; opacity: .75; font-weight: 500; }
        
        .body { padding: 32px 40px; flex: 1; }
        
        .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 24px; page-break-inside: avoid; }
        .info-item { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 16px; box-shadow: 0 1px 3px rgba(0,0,0,0.02); }
        .info-label { font-size: 11px; color: #64748b; text-transform: uppercase; letter-spacing: .06em; font-weight: 700; margin-bottom: 4px; }
        .info-value { font-size: 15px; font-weight: 700; color: #0f172a; }
        
        .score-card { text-align: center; color: white; border-radius: 12px; padding: 24px; margin-bottom: 24px; page-break-inside: avoid; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        .score-num { font-size: 52px; font-weight: 800; line-height: 1; letter-spacing: -0.02em; text-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .score-label { font-size: 13px; opacity: .95; margin-top: 8px; font-weight: 500; }
        
        .section-title { font-size: 18px; font-weight: 800; color: #05624e; margin-bottom: 16px; padding-bottom: 8px; border-bottom: 2px solid #e2e8f0; page-break-after: avoid; }
        
        table { width: 100%; border-collapse: collapse; font-size: 11px; margin-bottom: 24px; }
        th { padding: 12px 16px; text-align: left; font-size: 11px; text-transform: uppercase; color: #475569; font-weight: 700; letter-spacing:.05em; background: #f8fafc; border-bottom: 2px solid #cbd5e1; }
        td { padding: 14px 16px; color: #334155; }
        
        .action-card { background: #ffffff; padding: 18px; margin-bottom: 12px; border-radius: 8px; page-break-inside: avoid; border: 1px solid #e2e8f0; box-shadow: 0 2px 5px rgba(0,0,0,0.02); }
        .action-title { font-weight: 700; font-size: 14px; color: #0f172a; margin-bottom: 6px; }
        .action-desc { font-size: 13px; color: #475569; line-height: 1.6; }
        .action-meta { font-size: 11px; color: #64748b; margin-top: 10px; text-transform: uppercase; font-weight: 700; letter-spacing:.02em; }
        
        .footer { text-align: center; font-size: 11px; color: #94a3b8; padding: 24px 48px; background: white; border-top: 1px solid #f1f5f9; }

        .floating-print-btn {
            position: fixed;
            bottom: 30px;
            right: 30px;
            background: #05624e;
            color: white;
            padding: 14px 24px;
            border-radius: 50px;
            font-family: inherit;
            font-weight: 700;
            font-size: 14px;
            border: none;
            cursor: pointer;
            box-shadow: 0 4px 15px rgba(5, 98, 78, 0.3);
            z-index: 1000;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .floating-print-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(5, 98, 78, 0.4);
        }
        
        @media print { 
            body { background: transparent; margin: 0; padding: 0; } 
            .page { 
                box-shadow: none; margin: 0; width: 100%; height: 100%; 
                page-break-after: always; padding: 0; border: none;
            }
            .page:last-child { page-break-after: auto; }
            * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; color-adjust: exact !important; }
            ::-webkit-scrollbar { display: none; }
            .floating-print-btn { display: none !important; }
        }
        ";
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
            ? "<div style='background:#ecfdf5;border:1px solid #05624e;border-radius:8px;padding:14px 20px;margin-bottom:32px;font-size:14px;color:#05624e; page-break-inside: avoid;'>
               <strong>📂 Program Scope:</strong> {$program->name} &nbsp;|&nbsp; Template: " . ($program->template?->name ?? 'N/A') . "</div>"
            : '';

        // Build pillar rows with visual bars
        $pillarRows = '';
        if ($latest && isset($latest['pillar_scores'])) {
            foreach ($latest['pillar_scores'] as $pillar) {
                $pct = min(100, $pillar['percentage'] ?? 0);
                if ($pct >= 70) {
                    $barColor = '#10b981';
                    $label = 'Good';
                    $labelColor = '#065f46';
                    $bgColor = '#f0fdf4';
                } elseif ($pct >= 45) {
                    $barColor = '#f59e0b';
                    $label = 'Needs Work';
                    $labelColor = '#92400e';
                    $bgColor = '#fffbeb';
                } else {
                    $barColor = '#ef4444';
                    $label = '⚠ Weak';
                    $labelColor = '#991b1b';
                    $bgColor = '#fef2f2';
                }

                $pillarRows .= "
                    <tr style='background:{$bgColor}; page-break-inside: avoid;'>
                        <td style='padding:14px 16px;font-weight:700;color:#0f172a; border-bottom: 2px solid white;'>{$pillar['pillar_name']}</td>
                        <td style='padding:14px 16px;text-align:center; border-bottom: 2px solid white; font-weight: 500;'>{$pillar['earned']} / {$pillar['max_score']}</td>
                        <td style='padding:14px 16px;min-width:200px; border-bottom: 2px solid white;'>
                            <div style='background:#cbd5e1;border-radius:999px;height:8px;overflow:hidden; margin-top: 4px;'>
                                <div style='width:{$pct}%;background:{$barColor};height:100%;border-radius:999px;'></div>
                            </div>
                            <span style='font-size:11px;color:#64748b;margin-top:6px;display:inline-block;font-weight:600;'>{$pct}%</span>
                        </td>
                        <td style='padding:14px 16px;font-weight:800;color:{$labelColor};font-size:12px; border-bottom: 2px solid white; text-transform: uppercase;'>{$label}</td>
                        <td style='padding:14px 16px;text-align:center;color:#64748b;font-size:12px; border-bottom: 2px solid white; font-weight:600;'>{$pillar['weight']}%</td>
                    </tr>
                ";
            }
        }

        $historyRows = '';
        foreach ($history as $h) {
            $score = $h['total_score'] ?? 'N/A';
            $hRisk = !empty($h['risk_level']) ? $h['risk_level'] : 'Not Assessed';
            $historyRows .= "
                <tr style='page-break-inside: avoid;'>
                    <td style='border-bottom: 1px solid #e2e8f0; font-weight: 500; color: #0f172a;'>{$h['template_name']}</td>
                    <td style='border-bottom: 1px solid #e2e8f0; font-weight:800; color: #05624e;'>{$score}</td>
                    <td style='border-bottom: 1px solid #e2e8f0;'>
                        <span style='background: #f1f5f9; padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: 600; color: #334155;'>{$hRisk}</span>
                    </td>
                    <td style='color:#64748b; border-bottom: 1px solid #e2e8f0;'>{$h['completed_at']}</td>
                </tr>
            ";
        }

        $score = isset($latest['score']) ? round($latest['score'], 1) : 'N/A';
        $riskLevel = $latest['risk_level'] ?? 'Not Assessed';
        $completedAt = $latest['completed_at'] ?? 'N/A';
        $generatedAt = now()->format('Y-m-d H:i');

        // Determine score card color
        $scoreColor = is_numeric($score) ? ($score >= 70 ? '#05624e' : ($score >= 45 ? '#d97706' : '#dc2626')) : '#64748b';

        // Build Recommended Actions (Force to Page 2)
        $actionsHtml = '';
        if ($latest && !empty($latest['recommended_actions'])) {
            $actionsHtml .= "<div style='margin-top: 36px; page-break-before: always;'><div class='section-title'>💡 Recommended Actions to Improve</div>";
            foreach ($latest['recommended_actions'] as $action) {
                $pColor = $action['priority'] === 'high' ? '#ef4444' : ($action['priority'] === 'medium' ? '#f59e0b' : '#3b82f6');
                $actionsHtml .= "
                <div class='action-card' style='border-left: 5px solid {$pColor};'>
                    <div class='action-title'>{$action['title']}</div>
                    <div class='action-desc'>{$action['description']}</div>
                    <div class='action-meta'>Pillar: {$action['pillar']} &nbsp;|&nbsp; Impact: +{$action['points']} pts</div>
                </div>";
            }
            $actionsHtml .= "</div>";
        }

        $innerHtml = "
    <div class='page'>
        <div class='cover'>
            <div style='font-size:12px;opacity:.8;margin-bottom:12px;text-transform:uppercase;letter-spacing:.12em;font-weight:600;'>Investment Readiness Platform</div>
            <h1>Readiness Report</h1>
            <p style='font-size:18px;font-weight:700;margin-top:8px;'>{$company['company_name']}</p>
            <p style='opacity:.9; margin-top: 4px;'>{$company['industry']} &nbsp;|&nbsp; {$company['email']}</p>
            <div class='meta'>Generated: {$generatedAt}</div>
        </div>
        
        <div class='body'>
            {$programBanner}
            
            <div class='info-grid'>
                <div class='info-item'><div class='info-label'>Registration No.</div><div class='info-value'>{$company['registration_number']}</div></div>
                <div class='info-item'><div class='info-label'>Contact Person</div><div class='info-value'>{$company['contact_person']}</div></div>
            </div>

            <div class='score-card' style='background: {$scoreColor};'>
                <div style='font-size:13px;opacity:.95;text-transform:uppercase;letter-spacing:.08em;margin-bottom:12px;font-weight:600;'>Overall Readiness Score</div>
                <div class='score-num'>{$score}</div>
                <div class='score-label'>Risk Level: <strong>{$riskLevel}</strong> &nbsp;|&nbsp; Assessed: {$completedAt}</div>
            </div>

            <div style='margin-top: 36px;'>
                <div class='section-title'>📊 Pillar Breakdown</div>
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

            " . (!empty($history) ? "
            <div style='margin-top: 36px; page-break-inside: avoid;'>
                <div class='section-title'>📋 Assessment History</div>
                <table>
                    <thead><tr><th>Template</th><th>Score</th><th>Risk Level</th><th>Date</th></tr></thead>
                    <tbody>{$historyRows}</tbody>
                </table>
            </div>" : "") . "
            
        </div>
        
        <div class='footer'>
            Generated by IRIP Investment Readiness Platform &nbsp;|&nbsp; Confidential Document
        </div>
    </div>";

        if ($innerOnly)
            return $innerHtml;

        $css = $this->getSharedPdfCss();

        return "<!DOCTYPE html>
<html>
<head>
    <title>Readiness Report — {$company['company_name']}</title>
    <meta charset='UTF-8'>
    <style>{$css}</style>
</head>
<body>
    {$innerHtml}
    
    <button class='floating-print-btn' onclick='window.print()'>
        <svg width='18' height='18' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'><polyline points='6 9 6 2 18 2 18 9'></polyline><path d='M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2'></path><rect x='6' y='14' width='12' height='8'></rect></svg>
        Save as PDF
    </button>
</body>
</html>";
    }

    /**
     * Generate HTML for portfolio report (Batch individual SME reports)
     */
    private function generatePortfolioReportHtml($allSmeData, $program = null)
    {
        if (empty($allSmeData)) {
            return "<html><head><style>body { font-family: sans-serif; padding: 40px; color: #333; }</style></head><body><h2>No SMEs assessed yet in this program.</h2></body></html>";
        }

        $pagesHtml = '';
        foreach ($allSmeData as $index => $data) {
            $pagesHtml .= $this->generateReadinessReportHtml($data, $program, true);
        }

        $css = $this->getSharedPdfCss();

        return "<!DOCTYPE html>
<html>
<head>
    <title>Batch Readiness Reports</title>
    <meta charset='UTF-8'>
    <style>{$css}</style>
</head>
<body>
    {$pagesHtml}
    
    <button class='floating-print-btn' onclick='window.print()'>
        <svg width='18' height='18' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'><polyline points='6 9 6 2 18 2 18 9'></polyline><path d='M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2'></path><rect x='6' y='14' width='12' height='8'></rect></svg>
        Save as PDF
    </button>
</body>
</html>";
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

        // Pre-load ALL completed assessments for this program's template in ONE query
        // to prevent N+1 (was querying per SME inside the loop)
        $smeIds = $enrollments->pluck('sme_id')->filter();
        $allAssessments = Assessment::whereIn('sme_id', $smeIds)
            ->where('template_id', $program->template_id)
            ->where('status', 'Completed')
            ->latest()
            ->get()
            ->unique('sme_id') // keep only latest per SME
            ->keyBy('sme_id');

        $smeData = [];
        $totalScore = 0;
        $completedCount = 0;
        $t = $this->assessmentService->getThresholds($program->id);

        foreach ($enrollments as $enrollment) {
            $sme = $enrollment->smeProfile;
            if (!$sme)
                continue;

            // Use pre-loaded collection instead of DB query per SME
            $assessment = $allAssessments->get($sme->id);

            $pillarScores = $assessment ? $this->assessmentService->calculatePillarScores($assessment, $t) : [];

            if ($assessment) {
                $totalScore += $assessment->total_score;
                $completedCount++;
            }

            $smeData[] = [
                'sme_id'            => $sme->id,
                'company_name'      => $sme->company_name,
                'contact_name'      => $sme->user?->full_name,
                'email'             => $sme->user?->email,
                'industry'          => $sme->industry,
                'enrollment_status' => $enrollment->status,
                'enrolled_at'       => $enrollment->enrollment_date?->format('Y-m-d'),
                'assessment_score'  => $assessment?->total_score,
                'risk_level'        => $assessment ? $this->assessmentService->getThresholdLabel($assessment->total_score, $t) : 'N/A',
                'completed_at'      => $assessment?->completed_at?->format('Y-m-d'),
                'pillar_scores'     => $pillarScores
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