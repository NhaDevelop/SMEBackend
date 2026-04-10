<?php

namespace App\Services;

use App\Models\Assessment;
use App\Models\AssessmentResponse;
use App\Models\Pillar;
use App\Models\Program;
use App\Models\FrameworkSetting;
use Illuminate\Support\Facades\Log;

class AssessmentService
{
    /**
     * Get the thresholds configuration for a program or global default.
     */
    public function getThresholds(?int $programId = null): array
    {
        if ($programId) {
            $program = Program::find($programId);
            if ($program && !empty($program->thresholds)) {
                return $program->thresholds;
            }
        }

        $settings = FrameworkSetting::where('key', 'framework_config')->first();
        return $settings?->value['thresholds'] ?? FrameworkSetting::where('key', 'thresholds')->first()?->value ?? [
            ['id' => 'investor', 'min' => 80, 'max' => 100, 'label' => 'Investor Ready', 'color' => '#10b981'],
            ['id' => 'near', 'min' => 60, 'max' => 79, 'label' => 'Near Ready', 'color' => '#f59e0b'],
            ['id' => 'early', 'min' => 40, 'max' => 59, 'label' => 'Early Stage', 'color' => '#0d9488'],
            ['id' => 'pre', 'min' => 0, 'max' => 39, 'label' => 'Pre-Investment', 'color' => '#e11d48'],
        ];
    }

    /**
     * Map a numeric score to the nearest threshold label.
     */
    public function getThresholdLabel(float $score, array $thresholds): string
    {
        $sorted = collect($thresholds)->sortByDesc('min')->values();
        foreach ($sorted as $t) {
            $t = (array) $t;
            if ($score >= (float)$t['min']) {
                return $t['label'];
            }
        }
        return 'Pre-Investment';
    }

    /**
     * Map a score to a simple Low / Medium / High financial-risk label.
     */
    public function getFinancialRisk(float $score, array $thresholds): string
    {
        $sorted = collect($thresholds)->sortByDesc('min')->values();
        foreach ($sorted as $t) {
            $t = (array) $t;
            if ($score >= (float)$t['min']) {
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
     * Calculate per-pillar scores for an assessment.
     * Returns array of [ id, name, score (0-100), weight, riskLevel, earned, max ]
     */
    public function calculatePillarScores(Assessment $assessment, array $thresholds): array
    {
        $pillars = Pillar::all()->keyBy('id');
        $responses = AssessmentResponse::where('assessment_id', $assessment->id)
            ->with('question')
            ->get();

        $grouped = [];
        foreach ($responses as $r) {
            if (!$r->question) continue;
            $pid = $r->question->pillar_id;
            $grouped[$pid] ??= ['earned' => 0, 'max' => 0];
            $grouped[$pid]['earned'] += (float)$r->score_awarded;
            $grouped[$pid]['max'] += (float)$r->question->weight;
        }

        $result = [];
        foreach ($pillars as $p) {
            $data = $grouped[$p->id] ?? ['earned' => 0, 'max' => 0];
            $score = $data['max'] > 0 ? round(($data['earned'] / $data['max']) * 100, 1) : 0;
            
            $result[] = [
                'id'          => $p->id,
                'name'        => $p->name,
                'pillar_name' => $p->name,          // Compatibility with PDF Reports
                'score'       => $score,             // PERCENTAGE (0-100) — used by all dashboards
                'percentage'  => $score,             // Alias for score — compatibility key
                'earned'      => $data['earned'],    // Raw earned points — for PDF display
                'max'         => $data['max'],       // Raw max points — for PDF display
                'max_score'   => $data['max'],       // Alias for PDF Reports
                'riskLevel'   => $this->getThresholdLabel($score, $thresholds),
                'weight'      => (float)$p->weight,
            ];
        }

        return $result;
    }

    /**
     * Calculate top recommended actions for an assessment based on low-scoring indicators.
     */
    public function calculateTopActions(Assessment $assessment, int $limit = 5): array
    {
        $responses = AssessmentResponse::where('assessment_id', $assessment->id)
            ->with('question')
            ->get();

        $pillars = Pillar::all()->keyBy('id');

        $gaps = $responses->filter(function ($r) {
            return $r->question && $r->question->weight > 0 && ($r->score_awarded / $r->question->weight) <= 0.5;
        })->map(function ($r) use ($pillars) {
            $pModel = $pillars->get($r->question->pillar_id);
            $pillarName = $pModel ? $pModel->name : 'Unknown';
            
            $max = (float)$r->question->weight;
            $earned = (float)$r->score_awarded;
            $ratio = $max > 0 ? ($earned / $max) : 1;
            
            return [
                'id' => 'gap_' . $r->id,
                'title' => 'Improve: ' . ($r->question->text ?? $r->question->title ?? 'Unknown Question'),
                'description' => 'Your current score for this indicator is ' . round($ratio * 100) . '%. Addressing this gap could improve your overall readiness by ' . round($max - $earned, 1) . ' points.',
                'priority' => $ratio <= 0.2 ? 'high' : ($ratio <= 0.5 ? 'medium' : 'low'),
                'pillarRisk' => $ratio <= 0.2 ? 'high' : ($ratio <= 0.5 ? 'medium' : 'low'),
                'pillar' => $pillarName,
                'impact' => round(($max - $earned), 1),
                'points' => round(($max - $earned), 1),
                'status' => 'pending'
            ];
        })->sortByDesc('impact')->values();

        return $gaps->take($limit)->toArray();
    }

    /**
     * Generate detailed SME report data (consolidated logic).
     */
    public function generateSmeReportData($sme, ?int $programId = null): array
    {
        $program = $programId ? Program::find($programId) : null;
        $templateId = $program ? $program->template_id : null;

        $completedAssessments = $sme->assessments->where('status', 'Completed');

        $latestAssessment = $templateId
            ? $completedAssessments->where('template_id', $templateId)->sortByDesc('completed_at')->first()
            : $completedAssessments->sortByDesc('completed_at')->first();

        $thresholds = $this->getThresholds($latestAssessment?->program_id ?? $programId);
        $pillarScores = $latestAssessment ? $this->calculatePillarScores($latestAssessment, $thresholds) : [];

        // Assessment history
        $assessmentHistory = $completedAssessments
            ->sortByDesc('completed_at')
            ->map(function ($assessment) {
                $t = $this->getThresholds($assessment->program_id);
                return [
                    'assessment_id' => $assessment->id,
                    'template_name' => $assessment->template?->name,
                    'total_score'   => round($assessment->total_score, 1),
                    'risk_level'    => $this->getThresholdLabel($assessment->total_score, $t),
                    'completed_at'  => $assessment->completed_at?->format('Y-m-d'),
                ];
            })->values();

        // Programs enrolled
        $programs = $sme->enrollments->map(function ($enrollment) {
            return [
                'program_name' => $enrollment->program?->name,
                'status'       => $enrollment->status,
                'enrolled_at'  => $enrollment->enrollment_date?->format('Y-m-d'),
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
                'id'                  => $latestAssessment->id,
                'score'               => round($latestAssessment->total_score, 1),
                'risk_level'          => $this->getThresholdLabel($latestAssessment->total_score, $thresholds),
                'completed_at'        => $latestAssessment->completed_at?->format('Y-m-d'),
                'pillar_scores'       => $pillarScores,
                'recommended_actions' => $this->calculateTopActions($latestAssessment, 3),
            ] : null,
            'assessment_history' => $assessmentHistory,
            'programs'           => $programs,
        ];
    }

    /**
     * Calculate total weighted score from pillar statistics.
     */
    public function calculateTotalScore(array $pillarScores): float
    {
        $totalScore = 0;
        foreach ($pillarScores as $p) {
            // (Pillar Score / 100) * Pillar Weight
            $weightedContribution = ($p['score'] * $p['weight']) / 100;
            $totalScore += $weightedContribution;
        }
        
        return min(100, round($totalScore, 2));
    }

    /**
     * Helper to get top performing pillars.
     */
    public function getTopPillars(array $pillarScores, int $count = 4): array
    {
        return collect($pillarScores)
            ->sortByDesc('score')
            ->take($count)
            ->map(function($p) {
                return [
                    'name' => explode(' ', trim($p['name']))[0], // First word only for UI
                    'full_name' => $p['name'],
                    'score' => $p['score']
                ];
            })
            ->values()
            ->toArray();
    }
}
