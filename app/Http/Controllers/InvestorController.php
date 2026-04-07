<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Assessment;
use App\Models\AssessmentResponse;
use App\Models\Pillar;
use App\Models\SmeProfile;
use App\Models\Program;
use App\Models\ProgramEnrollment;
use App\Models\FrameworkSetting;

class InvestorController extends Controller
{
    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function getThresholds(): array
    {
        $settings = FrameworkSetting::where('key', 'framework_config')->first();
        return $settings?->value['thresholds'] ?? FrameworkSetting::where('key', 'thresholds')->first()?->value ?? [
            ['id' => 'investor', 'min' => 80, 'max' => 100, 'label' => 'Investor Ready', 'color' => '#10b981'],
            ['id' => 'near', 'min' => 60, 'max' => 79, 'label' => 'Near Ready', 'color' => '#f59e0b'],
            ['id' => 'early', 'min' => 40, 'max' => 59, 'label' => 'Early Stage', 'color' => '#0d9488'],
            ['id' => 'pre', 'min' => 0, 'max' => 39, 'label' => 'Pre-Investment', 'color' => '#e11d48'],
        ];
    }

    /** Map a numeric score to the nearest threshold label, with no gaps (open-ended upper bound). */
    private function getThresholdLabel(float $score, array $thresholds): string
    {
        $sorted = collect($thresholds)->sortByDesc('min')->values();
        foreach ($sorted as $t) {
            $t = (array) $t;
            if ($score >= $t['min'])
                return $t['label'];
        }
        return 'Pre-Investment';
    }

    /** Map a score to a simple Low / Medium / High financial-risk label. */
    private function getFinancialRisk(float $score, array $thresholds): string
    {
        $sorted = collect($thresholds)->sortByDesc('min')->values();
        foreach ($sorted as $t) {
            $t = (array) $t;
            if ($score >= $t['min']) {
                return match ($t['id']) {
                    'investor' => 'Low',
                    'near' => 'Low',
                    'early' => 'Medium',
                    default => 'High',
                };
            }
        }
        return 'High';
    }

    /**
     * Calculate per-pillar scores for a completed assessment.
     * Returns array of [ id, name, score (0-100), weight, riskLevel ]
     */
    private function calcPillarScores(Assessment $assessment, array $thresholds): array
    {
        $pillars = Pillar::all()->keyBy('id');
        $responses = AssessmentResponse::where('assessment_id', $assessment->id)
            ->with('question')
            ->get();

        $grouped = [];
        foreach ($responses as $r) {
            if (!$r->question)
                continue;
            $pid = $r->question->pillar_id;
            $grouped[$pid] ??= ['earned' => 0, 'max' => 0];
            $grouped[$pid]['earned'] += (float) $r->score_awarded;
            $grouped[$pid]['max'] += (float) $r->question->weight;
        }

        $result = [];
        foreach ($pillars as $p) {
            $data = $grouped[$p->id] ?? ['earned' => 0, 'max' => 0];
            $score = $data['max'] > 0 ? round(($data['earned'] / $data['max']) * 100, 1) : 0;
            $result[] = [
                'id' => $p->id,
                'name' => $p->name,
                'score' => $score,
                'weight' => (float) $p->weight,
                'riskLevel' => $this->getThresholdLabel($score, $thresholds),
            ];
        }
        return $result;
    }

    // ─── Endpoints ────────────────────────────────────────────────────────────

    /**
     * GET /api/investor/dealflow
     * Returns every active SME enriched with real growth rate, pillar scores,
     * score history and risk classification — all from real assessment data.
     */
    public function dealflow(Request $request)
    {
        $thresholds = $this->getThresholds();

        $investorProfile = auth()->user()->investorProfile;
        $investorId = $investorProfile?->id;

        // Get program IDs the current investor is enrolled in
        $enrolledProgramIds = [];
        if ($investorId) {
            $enrolledProgramIds = ProgramEnrollment::where('investor_id', $investorId)
                ->pluck('program_id')
                ->toArray();
        }

        $query = User::where('role', 'SME')
            ->where('status', 'ACTIVE');

        // Scope to enrolled programs if investor is logged in
        if ($investorId) {
            $programFilterId = $request->input('program_id');

            $query->whereHas('smeProfile.enrollments', function ($q) use ($enrolledProgramIds, $programFilterId) {
                if ($programFilterId) {
                    $q->where('program_id', $programFilterId);
                } else {
                    $q->whereIn('program_id', $enrolledProgramIds);
                }
            });
        }

        $programFilterId = $investorId ? $request->input('program_id') : null;
        $targetTemplateId = $programFilterId ? Program::find($programFilterId)?->template_id : null;

        $smes = $query->with([
            'smeProfile',
            'smeProfile.enrollments',
            'smeProfile.assessments' => function ($query) {
                $query->where('status', 'Completed')->orderBy('completed_at', 'asc')->with('program');
            }
        ])
            ->get()
            ->map(function (User $user) use ($thresholds, $targetTemplateId) {
                $profile = $user->smeProfile;
                if (!$profile)
                    return null;

                // Get program IDs this SME is enrolled in
                $smeProgramIds = $profile->enrollments->pluck('program_id')->toArray();

                // ── Filter assessments by targeted template (if program filter active) ──
                $assessments = collect($profile->assessments ?? []);
                if ($targetTemplateId) {
                    $assessments = $assessments->where('template_id', $targetTemplateId);
                }

                $latestAssessment = $assessments->last();
                // second-to-last: slice from end-2, take 1
                $prevAssessment = $assessments->count() >= 2 ? $assessments->slice(-2, 1)->first() : null;

                // ── Current readiness score ───────────────────────────────────
                // When a program filter is active, ONLY count scores from that
                // program's template. Never fall back to the profile's stored
                // readiness_score — that value belongs to a different program.
                $currentScore = $latestAssessment
                    ? round((float) $latestAssessment->total_score, 1)
                    : ($targetTemplateId ? 0.0 : round((float) ($profile->readiness_score ?? 0), 1));

                // ── Per-pillar breakdown ──────────────────────────────────────
                $smeThresholds = ($latestAssessment && $latestAssessment->program && !empty($latestAssessment->program->thresholds))
                    ? $latestAssessment->program->thresholds
                    : $thresholds;

                $pillars = $latestAssessment
                    ? $this->calcPillarScores($latestAssessment, $smeThresholds)
                    : [];

                // ── Risk classification ───────────────────────────────────────
                $financialRisk = $this->getFinancialRisk($currentScore, $smeThresholds);
                $readinessLabel = $this->getThresholdLabel($currentScore, $smeThresholds);

                // ── Score history (for sparkline / trend) ────────────────────
                $scoreHistory = $assessments->map(fn($a) => [
                    'date' => $a->completed_at->format('Y-m-d'),
                    'score' => round((float) $a->total_score, 1),
                ])->values()->toArray();

                $readinessHistory = array_column($scoreHistory, 'score');

                // ── Last assessed date ────────────────────────────────────────
                $lastAssessedDate = $latestAssessment
                    ? $latestAssessment->completed_at->format('Y-m-d')
                    : null;

                // ── Real growth rate: Intrinsic Potential from Pillars ──
                $prevAssessment = $assessments->count() >= 2 ? $assessments->slice(-2, 1)->first() : null;
                $prevScore = $prevAssessment ? (float) $prevAssessment->total_score : null;
                $growthRate = $this->calculateGrowthPotential($pillars);
                $growthPlot = max(0, min(100, $growthRate));

                // ── Program Enrollments ──────────────────────────────────────
                $programIds = ProgramEnrollment::where('sme_id', $profile->id)
                    ->pluck('program_id')
                    ->toArray();

                return [
                    // Identity
                    'id' => $profile->id,
                    'name' => $profile->company_name ?? $user->full_name,
                    'industry' => $profile->industry ?? 'Uncategorized',
                    'location' => $profile->address ?? 'N/A',
                    'stage' => $profile->stage ?? 'Seed',
                    'description' => "Active SME in {$profile->industry}.",
                    'fundingNeeded' => 0,

                    // Scores & Risk
                    'score' => $currentScore,
                    'financialRisk' => $financialRisk,
                    'readinessLabel' => $readinessLabel,

                    // REAL growth data (from assessment history)
                    'growthRate' => $growthPlot,       // % change between last two assessments
                    'rawGrowthRate' => $growthRate,       // actual % (may exceed ±100)
                    'prevScore' => $prevScore,        // previous assessment score (null if first)
                    'assessmentCount' => $assessments->count(),

                    // History (for sparkline)
                    'readinessHistory' => $readinessHistory,
                    'scoreHistory' => $scoreHistory,
                    'readinessProgress' => $currentScore,

                    // Pillar breakdown
                    'pillars' => $pillars,

                    // Metadata
                    'status' => 'Active',
                    'lastAssessedDate' => $lastAssessedDate,
                    'lastUpdated' => $lastAssessedDate ?? $user->updated_at->format('Y-m-d'),
                    'keyStrength' => count($pillars) > 0
                        ? collect($pillars)->sortByDesc('score')->first()['name'] ?? 'N/A'
                        : 'N/A',

                    // Financials placeholder (extend when financial data model exists)
                    'financials' => [
                        'revenue' => 'N/A',
                        'profit' => 'N/A',
                        'growth' => ($growthRate >= 0 ? '+' : '') . $growthRate . '%',
                    ],

                    // Backward-compat snake_case
                    'readiness_score' => $currentScore,
                    'risk_level' => $latestAssessment?->risk_level ?? 'Medium',
                    'programIds' => $smeProgramIds,
                    // User ID needed for report generation (readiness endpoint uses user_id)
                    'user_id' => $user->id,
                ];
            })
            ->filter()   // remove nulls (SMEs without profiles)
            ->values();

        return $this->success([
            'data' => $smes,
            'thresholds' => $thresholds,
        ], 'Dealflow retrieved successfully');
    }

    /**
     * GET /api/investor/analytics
     * Portfolio-level aggregates for the analytics page.
     */
    public function analytics(Request $request)
    {
        $thresholds = $this->getThresholds();

        $investorProfile = auth()->user()->investorProfile;
        $investorId = $investorProfile?->id;

        // Get program IDs the current investor is enrolled in
        $enrolledProgramIds = [];
        if ($investorId) {
            $enrolledProgramIds = ProgramEnrollment::where('investor_id', $investorId)
                ->pluck('program_id')
                ->toArray();
        }

        // 1. Base Query with Role & Status
        $query = User::where('role', 'SME')
            ->where('status', 'ACTIVE');

        // Scope to enrolled programs if investor is logged in (Matches Dealflow logic)
        $programFilterId = $investorId ? $request->input('program_id') : null;
        $targetTemplateId = $programFilterId ? Program::find($programFilterId)?->template_id : null;

        if ($investorId) {
            $query->whereHas('smeProfile.enrollments', function ($q) use ($enrolledProgramIds, $programFilterId) {
                if ($programFilterId) {
                    $q->where('program_id', $programFilterId);
                } else {
                    $q->whereIn('program_id', $enrolledProgramIds);
                }
            });
        }

        // 2. Fetch Aggregates from Scoped Query
        $portfolioCount = $query->count();

        // Detailed SME List mapping
        $riskMetrics = [];
        foreach ($thresholds as $t) {
            $riskMetrics[strtolower(str_replace(' ', '_', $t['label']))] = 0;
        }

        $sortedThresholds = collect($thresholds)->sortByDesc('min');

        $smes = $query->with([
            'smeProfile',
            'smeProfile.assessments' => function ($q) {
                $q->where('status', 'Completed')->orderBy('completed_at', 'asc')->with('program');
            }
        ])
            ->get()
            ->map(function (User $user) use ($thresholds, $sortedThresholds, &$riskMetrics, $targetTemplateId) {
                $profile = $user->smeProfile;
                if (!$profile)
                    return null;

                // ── Filter assessments by targeted template (if program filter active) ──
                $assessments = collect($profile->assessments ?? []);
                if ($targetTemplateId) {
                    $assessments = $assessments->where('template_id', $targetTemplateId);
                }

                $latestAssessment = $assessments->last();

                // When a program filter is active, ONLY count scores from that
                // program's template. Never fall back to the profile's stored
                // readiness_score — that value belongs to a different program.
                $currentScore = $latestAssessment
                    ? round((float) $latestAssessment->total_score, 1)
                    : ($targetTemplateId ? 0.0 : round((float) ($profile->readiness_score ?? 0), 1));

                $smeThresholds = ($latestAssessment && $latestAssessment->program && !empty($latestAssessment->program->thresholds))
                    ? $latestAssessment->program->thresholds
                    : $thresholds;
                
                $smeSortedThresholds = collect($smeThresholds)->sortByDesc('min');

                // Update risk metrics counter
                $matched = $smeSortedThresholds->first(fn($t) => $currentScore >= (float) $t['min']);
                if ($matched) {
                    $key = strtolower(str_replace(' ', '_', $matched['label']));
                    $riskMetrics[$key] = ($riskMetrics[$key] ?? 0) + 1;
                }

                $prevAssessment = $assessments->count() >= 2 ? $assessments->slice(-2, 1)->first() : null;
                $prevScore = $prevAssessment ? (float) $prevAssessment->total_score : null;

                $growthRate = 0;
                if ($prevScore !== null && $prevScore > 0) {
                    $growthRate = round((($currentScore - $prevScore) / $prevScore) * 100, 1);
                }

                $pillars = $latestAssessment ? $this->calcPillarScores($latestAssessment, $smeThresholds) : [];

                return [
                    'id' => $profile->id,
                    'name' => $profile->company_name ?? $user->full_name,
                    'industry' => $profile->industry ?? 'Uncategorized',
                    'score' => $currentScore,
                    'financialRisk' => $this->getFinancialRisk($currentScore, $smeThresholds),
                    'readinessLabel' => $this->getThresholdLabel($currentScore, $smeThresholds),
                    'growthRate' => max(-100, min(100, $growthRate)),
                    'lastAssessedDate' => $latestAssessment ? $latestAssessment->completed_at->format('Y-m-d') : null,
                    'pillars' => $pillars,
                ];
            })
            ->filter()
            ->values();

        $avgScore = $smes->avg('score') ?? 0;

        return $this->success([
            'total_portfolio' => $portfolioCount,
            'average_readiness' => round($avgScore, 2),
            'sector_distribution' => SmeProfile::whereIn('user_id', (clone $query)->pluck('id'))
                ->selectRaw('industry, count(*) as count')
                ->groupBy('industry')
                ->get(),
            'risk_metrics' => $riskMetrics,
            'thresholds' => $thresholds,
            'smes' => $smes,
            // Nuxt compat keys
            'activeDealFlow' => $portfolioCount,
            'avgReadinessScore' => round($avgScore, 1),
        ], 'Analytics retrieved successfully');
    }

    /**
     * GET /api/investor/smes/{id}
     * Investor-accessible SME profile — same shape as admin show() but open to investors.
     */
    public function showSme(Request $request, $id)
    {
        $thresholds = $this->getThresholds();
        $programId = $request->input('program_id');
        $targetTemplateId = $programId ? Program::find($programId)?->template_id : null;

        // Find by SmeProfile ID first, as that is what is returned in dealflow 'id'
        $profile = SmeProfile::with([
            'user',
            'assessments' => function ($q) {
                $q->where('status', 'Completed')->latest();
            }
        ])->find($id);

        if (!$profile) {
            // Fallback to User ID for backward compatibility
            $user = User::with([
                'smeProfile',
                'smeProfile.assessments' => function ($q) {
                    $q->where('status', 'Completed')->latest();
                }
            ])->find($id);
            $profile = $user?->smeProfile;
        } else {
            $user = $profile->user;
        }

        // Filter assessments by targeted template (if program filter active)
        $allAssessments = Assessment::where('sme_id', $profile?->id)
            ->where('status', 'Completed')
            ->with(['template', 'program'])
            ->orderBy('completed_at', 'asc')
            ->get();

        $assessments = $targetTemplateId
            ? $allAssessments->where('template_id', $targetTemplateId)
            : $allAssessments;

        $latestAssessment = $assessments->last();
        $score = $latestAssessment ? (float) $latestAssessment->total_score : (float) ($profile?->readiness_score ?? 0);
        
        $smeThresholds = ($latestAssessment && $latestAssessment->program && !empty($latestAssessment->program->thresholds))
                    ? $latestAssessment->program->thresholds
                    : $thresholds;

        $scoreHistory = $assessments->map(function ($a) use ($thresholds) {
            $t = ($a->program && !empty($a->program->thresholds)) ? $a->program->thresholds : $thresholds;
            return [
                'date' => $a->completed_at->format('Y-m-d'),
                'score' => round((float) $a->total_score, 1),
                'template_name' => $a->template?->name ?? 'Standard Assessment',
                'risk_level' => $this->getThresholdLabel(round((float) $a->total_score, 1), $t),
            ];
        })->values()->toArray();

        $prevScore = $assessments->count() >= 2 ? (float) $assessments->slice(-2, 1)->first()->total_score : null;
        $pillars = $latestAssessment ? $this->calcPillarScores($latestAssessment, $smeThresholds) : [];
        $growthRate = $this->calculateGrowthPotential($pillars);

        return $this->success([
            'id' => $profile->id,
            'name' => $profile?->company_name ?? $user->full_name,
            'industry' => $profile?->industry ?? 'N/A',
            'location' => $profile?->address ?? 'N/A',
            'email' => $user->email,
            'stage' => $profile?->stage ?? 'Seed',
            'yearsInBusiness' => $profile?->years_in_business,
            'teamSize' => $profile?->team_size,
            'foundingDate' => ($profile && $profile->founding_date) ? $profile->founding_date->format('Y-m-d') : null,
            'websiteUrl' => $profile?->website_url,
            'registrationDocument' => $profile?->registration_document,
            'score' => $score,
            'riskLevel' => $this->getFinancialRisk($score, $smeThresholds),
            'readinessStatus' => $this->getThresholdLabel($score, $smeThresholds),
            'growthPotential' => $growthRate,
            'growthRate' => $growthRate,
            'prevScore' => $prevScore,
            'assessmentCount' => $assessments->count(),
            'lastAssessed' => $latestAssessment?->completed_at->format('Y-m-d') ?? 'N/A',
            'pillars' => $pillars,
            'scoreHistory' => $scoreHistory,
            'readinessHistory' => array_column($scoreHistory, 'score'),
            'assessments' => $assessments->map(fn($a) => [
                'id' => $a->id,
                'score' => round((float) $a->total_score, 1),
                'status' => $a->status,
                'completed_at' => $a->completed_at?->format('Y-m-d'),
                'template_id' => $a->template_id,
                'template_name' => $a->template?->name ?? 'Standard Assessment',
            ])->values()->toArray(),
            'thresholds' => $thresholds,
        ], 'SME profile retrieved successfully');
    }

    /**
     * GET /api/investor/smes/{id}/dashboard
     * Investor-accessible SME dashboard — returns pillar scores and action items.
     */
    public function smeDashboard(Request $request, $id)
    {
        $thresholds = $this->getThresholds();

        $profile = SmeProfile::with('user')->find($id);
        if (!$profile) {
            $user = User::with('smeProfile')->find($id);
            $profile = $user?->smeProfile;
        } else {
            $user = $profile->user;
        }

        if (!$user || $user->role !== 'SME') {
            return $this->error('User is not an SME or profile not found', 404);
        }

        $profile = $user->smeProfile;
        $programId = $request->input('program_id');
        $targetTemplateId = $programId ? Program::find($programId)?->template_id : null;

        $latestAssessment = \App\Models\Assessment::where('sme_id', $profile?->id)
            ->where('status', 'Completed')
            ->when($targetTemplateId, function ($q) use ($targetTemplateId) {
                return $q->where('template_id', $targetTemplateId);
            })
            ->with('program')
            ->latest('completed_at')
            ->first();

        $smeThresholds = ($latestAssessment && $latestAssessment->program && !empty($latestAssessment->program->thresholds))
            ? $latestAssessment->program->thresholds
            : $thresholds;

        $pillars = $latestAssessment ? $this->calcPillarScores($latestAssessment, $smeThresholds) : [];

        // Action items from low-scoring responses
        $actions = [];
        if ($latestAssessment) {
            $responses = \App\Models\AssessmentResponse::where('assessment_id', $latestAssessment->id)
                ->with('question')
                ->get();

            $gaps = $responses->filter(function ($r) {
                return $r->question && $r->question->weight > 0 && ($r->score_awarded / $r->question->weight) <= 0.5;
            })->map(function ($r) {
                $pModel = Pillar::find($r->question->pillar_id);
                $pillarName = $pModel ? $pModel->name : $r->question->pillar_id;
                $max = (float) $r->question->weight;
                $earned = (float) $r->score_awarded;
                $ratio = $max > 0 ? ($earned / $max) : 1;

                return [
                    'id' => 'gap_' . $r->id,
                    'title' => 'Improve: ' . $r->question->text,
                    'description' => 'Current score: ' . round($ratio * 100) . '%. Addressing this gap could improve overall readiness by ' . round($max - $earned, 1) . ' points.',
                    'priority' => $ratio <= 0.2 ? 'high' : ($ratio <= 0.5 ? 'medium' : 'low'),
                    'pillarRisk' => $ratio <= 0.2 ? 'high' : ($ratio <= 0.5 ? 'medium' : 'low'),
                    'pillar' => $pillarName,
                    'impact' => round(($max - $earned), 1),
                    'points' => round(($max - $earned), 1),
                    'status' => 'pending'
                ];
            })->sortByDesc('impact')->values()->take(10);

            $actions = $gaps->toArray();
        }

        return $this->success([
            'id' => $profile->id,
            'pillars' => $pillars,
            'actions' => $actions,
            'thresholds' => $thresholds,
        ], 'SME dashboard data retrieved successfully');
    }

    /**
     * POST /api/investor/programs/{id}/enroll
     */
    public function enrollProgram(Request $request, $id)
    {
        $program = \App\Models\Program::findOrFail($id);
        $investorProfile = auth()->user()->investorProfile;

        if (!$investorProfile) {
            return $this->error('Investor profile not found', 403);
        }

        // Enforce: Program must not be Finished
        if ($program->isFinished()) {
            return $this->error('This program has ended and is no longer accepting enrollments.', 403);
        }

        // Enforce: Enrollment deadline must not have passed
        if ($program->isEnrollmentClosed()) {
            return $this->error('The enrollment period for this program has closed. No new enrollments are being accepted.', 403);
        }

        $existing = \App\Models\ProgramEnrollment::where('program_id', $program->id)
            ->where('investor_id', $investorProfile->id)
            ->first();

        if ($existing) {
            return $this->error('You have already enrolled in this program', 409, ['status' => $existing->status]);
        }

        $enrollment = \App\Models\ProgramEnrollment::create([
            'program_id' => $program->id,
            'investor_id' => $investorProfile->id,
            'status' => 'Accepted',
            'enrollment_date' => now()
        ]);

        return $this->success($enrollment, 'Enrolled in program successfully', 201);
    }

    /**
     * GET /api/investor/programs
     * List published programs with live stats for investors.
     */
    public function programs()
    {
        $user = auth()->user();
        if (!$user) {
            return $this->error('Unauthenticated', 401);
        }

        $investorId = $user->investorProfile?->id;

        $programs = Program::where('status', 'Published')
            ->with(['template'])
            ->get()
            ->map(function ($program) use ($investorId) {
                // Count SMEs enrolled in this specific program
                $totalSmes = ProgramEnrollment::where('program_id', $program->id)
                    ->whereNotNull('sme_id')
                    ->count();

                // Check if CURRENT investor is enrolled
                $isEnrolled = false;
                $totalInvestors = ProgramEnrollment::where('program_id', $program->id)
                    ->whereNotNull('investor_id')
                    ->count();

                if ($investorId) {
                    $isEnrolled = ProgramEnrollment::where('program_id', $program->id)
                        ->where('investor_id', $investorId)
                        ->exists();
                }

                // Calculate average score if there's an associated template
                $completedAssessments = \App\Models\Assessment::where('template_id', $program->template_id)
                    ->whereIn('sme_id', ProgramEnrollment::where('program_id', $program->id)
                        ->whereNotNull('sme_id')
                        ->pluck('sme_id'))
                    ->where('status', 'Completed')
                    ->get();

                $completedCount = $completedAssessments->count();
                $avgScore = $completedCount > 0 ? round($completedAssessments->avg('total_score'), 1) : 0;
                $progress = $totalSmes > 0 ? round(($completedCount / $totalSmes) * 100) : 0;

                return [
                    'id' => $program->id,
                    'name' => $program->name,
                    'description' => $program->description,
                    'status' => 'Published',
                    'template' => $program->template?->name,
                    'sector' => $program->sector,
                    'investmentAmount' => $program->investment_amount,
                    'benefits' => $program->benefits,
                    'smesCount' => $totalSmes,
                    'investorsCount' => $totalInvestors,
                    'isEnrolled' => $isEnrolled,
                    'avgScore' => $avgScore,
                    'progress' => $progress,
                    'startDate' => $program->start_date?->format('Y-m-d'),
                    'endDate' => $program->end_date?->format('Y-m-d'),
                    'isEnrollmentClosed' => $program->isEnrollmentClosed(),
                    'isAssessmentPeriodOver' => $program->isAssessmentPeriodOver(),
                    'isFinished' => $program->isFinished(),
                    'isComingSoon' => $program->isComingSoon(),
                    'enrollmentDeadline' => $program->enrollment_deadline ? $program->enrollment_deadline->format('Y-m-d H:i:s') : null,
                ];
            });

        $enrolledPrograms = $programs->filter(fn($p) => $p['isEnrolled']);

        $stats = [
            'total' => $programs->count(),
            'active' => $enrolledPrograms->count(),
            'enrolled' => $enrolledPrograms->sum('smesCount'),
            'avgScore' => $enrolledPrograms->count() > 0 ? round($enrolledPrograms->avg('avgScore'), 1) : 0,
        ];

        return $this->success([
            'programs' => $programs,
            'stats' => $stats
        ], 'Programs retrieved successfully');
    }

    /**
     * Calculate intrinsic Growth Potential based on key scalable pillars.
     */
    private function calculateGrowthPotential(array $pillars): float
    {
        if (empty($pillars)) {
            return 0.0;
        }

        $targetPillars = ['Growth & Scalability', 'Market & Traction', 'Business Model'];
        $sum = 0;
        $count = 0;

        foreach ($pillars as $pillar) {
            if (in_array($pillar['name'], $targetPillars)) {
                $sum += (float) $pillar['score'];
                $count++;
            }
        }

        if ($count === 0) {
            // Fallback: average all pillars if targeting is missing
            foreach ($pillars as $pillar) {
                $sum += (float) $pillar['score'];
                $count++;
            }
        }

        return $count > 0 ? round($sum / $count, 1) : 0.0;
    }
}