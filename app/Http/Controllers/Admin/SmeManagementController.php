<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Assessment;
use App\Models\AssessmentResponse;
use App\Models\Pillar;
use App\Models\SmeProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

use App\Traits\ApiResponse;

class SmeManagementController extends Controller
{
    use ApiResponse, \App\Traits\AssessmentScoring;
    /**
     * GET /api/admin/smes/{id}
     * Fetch basic SME profile and user info.
     */
    public function show($id)
    {
        // Fetch dynamic thresholds
        $settings = \App\Models\FrameworkSetting::where('key', 'framework_config')->first();
        $thresholds = $settings ? ($settings->value['thresholds'] ?? []) : [
            ['id' => 'investor', 'label' => 'Investor Ready', 'min' => 80, 'max' => 100],
            ['id' => 'near', 'label' => 'Near Ready', 'min' => 60, 'max' => 79],
            ['id' => 'early', 'label' => 'Early Stage', 'min' => 40, 'max' => 59],
            ['id' => 'pre', 'label' => 'Pre-Investment', 'min' => 0, 'max' => 39],
        ];

        $user = User::with(['smeProfile', 'smeProfile.assessments' => function ($q) {
            $q->where('status', 'Completed')->latest();
        }])->findOrFail($id);

        if ($user->role !== 'SME') {
            return $this->error('User is not an SME', 400);
        }

        $profile = $user->smeProfile;

        $latestAssessment = $profile->assessments->first();
        $actualScore = $latestAssessment ? (float)$latestAssessment->total_score : 0;

        return $this->success([
            'id' => $user->id,
            'name' => $profile->company_name ?? $user->full_name,
            'industry' => $profile->industry ?? 'N/A',
            'location' => $profile->address ?? 'N/A',
            'email' => $user->email,
            'phone' => $user->phone,
            'registrationNumber' => $profile->registration_number,
            'yearsInBusiness' => $profile->years_in_business,
            'teamSize' => $profile->team_size,
            'stage' => $profile->stage,
            'foundingDate' => $profile->founding_date ? $profile->founding_date->format('Y-m-d') : null,
            'websiteUrl' => $profile->website_url,
            'score' => $actualScore,
            'riskLevel' => $latestAssessment ? $this->getRiskLabel($actualScore, $thresholds) : 'Not Assessed',
            'readinessStatus' => $latestAssessment ? $this->getRiskLabel($actualScore, $thresholds) : 'Needs Assessment',
            'lastAssessed' => $latestAssessment ? $latestAssessment->completed_at->format('Y-m-d') : 'Never',
            // Real growth rate: % change between last two COMPLETED assessments
            'growthPotential' => (function () use ($profile, $actualScore): float {
                $prev = Assessment::where('sme_id', $profile->id)
                    ->where('status', 'Completed')
                    ->orderByDesc('completed_at')
                    ->skip(1)->take(1)->first();
                if (!$prev || $prev->total_score == 0) return 0;
                return round((($actualScore - (float)$prev->total_score) / (float)$prev->total_score) * 100, 1);
            })(),
            'growthRate' => (function () use ($profile, $actualScore): float {
                $prev = Assessment::where('sme_id', $profile->id)
                    ->where('status', 'Completed')
                    ->orderByDesc('completed_at')
                    ->skip(1)->take(1)->first();
                if (!$prev || $prev->total_score == 0) return 0;
                return round((($actualScore - (float)$prev->total_score) / (float)$prev->total_score) * 100, 1);
            })(),
            // Score history for sparkline (completed only, chronological)
            'scoreHistory' => Assessment::where('sme_id', $profile->id)
                ->where('status', 'Completed')
                ->orderBy('completed_at', 'asc')
                ->get()
                ->map(fn($a) => [
                    'date'  => $a->completed_at->format('Y-m-d'),
                    'score' => round((float) $a->total_score, 1),
                ])->values()->toArray(),
            'readinessHistory' => Assessment::where('sme_id', $profile->id)
                ->where('status', 'Completed')
                ->orderBy('completed_at', 'asc')
                ->pluck('total_score')
                ->map(fn($s) => round((float)$s, 1))
                ->values()->toArray(),
            // COMPLETED assessments ONLY — in-progress ones lack completed_at (shows as 'Invalid Date')
            'assessments' => $profile->assessments()
                ->with('template')
                ->where('status', 'Completed')
                ->orderByDesc('completed_at')
                ->get()
                ->map(function ($a) use ($thresholds) {
                    return [
                        'id'           => $a->id,
                        'score'        => round((float)$a->total_score, 1),
                        'total_score'  => round((float)$a->total_score, 1),
                        'status'       => $a->status,
                        'completedAt'  => $a->completed_at->format('Y-m-d'),
                        'completed_at' => $a->completed_at->format('Y-m-d'),
                        'templateName' => $a->template ? $a->template->name : 'Standard Assessment',
                        'template_name'=> $a->template ? $a->template->name : 'Standard Assessment',
                    ];
                })
        ]);
    }

    /**
     * GET /api/admin/smes/{id}/dashboard
     * Fetch analytics for the SME: radar, progress, actions.
     */
    public function dashboard($id)
    {
        $user = User::with('smeProfile')->findOrFail($id);
        $profile = $user->smeProfile;

        // Fetch dynamic thresholds
        $settings = \App\Models\FrameworkSetting::where('key', 'framework_config')->first();
        $thresholds = $settings ? ($settings->value['thresholds'] ?? []) : [
            ['id' => 'investor', 'label' => 'Investor Ready', 'min' => 80, 'max' => 100],
            ['id' => 'near', 'label' => 'Near Ready', 'min' => 60, 'max' => 79],
            ['id' => 'early', 'label' => 'Early Stage', 'min' => 40, 'max' => 59],
            ['id' => 'pre', 'label' => 'Pre-Investment', 'min' => 0, 'max' => 39],
        ];

        // 1. Fetch Latest Assessment & Responses
        $latestAssessment = Assessment::where('sme_id', $profile->id)
            ->where('status', 'Completed')
            ->latest()
            ->first();

        $pillarStats = [];
        if ($latestAssessment) {
            $pillarStats = $this->calculatePillarScores($latestAssessment, $thresholds);
        }
        else {
            // No assessment yet
            $pillars = Pillar::all();
            foreach ($pillars as $p) {
                $pillarStats[] = [
                    'id' => $p->id,
                    'name' => $p->name,
                    'score' => 0,
                    'weight' => (float)$p->weight,
                    'riskLevel' => 'Pre-Investment'
                ];
            }
        }

        // 2. Progress Data
        $progress = Assessment::where('sme_id', $profile->id)
            ->where('status', 'Completed')
            ->orderBy('completed_at', 'asc')
            ->get()
            ->map(function ($a) {
            return [
            'month' => $a->completed_at->format('M'),
            'score' => (float)$a->total_score
            ];
        });

        // 3. Action Items (Derived from responses scoring < 50% of weight)
        $actions = [];
        if ($latestAssessment) {
            $responses = AssessmentResponse::where('assessment_id', $latestAssessment->id)
                ->with('question')
                ->get();

            $gaps = $responses->filter(function ($r) {
                return $r->question && $r->question->weight > 0 && ($r->score_awarded / $r->question->weight) <= 0.5;
            })->map(function ($r) {
                $pModel = Pillar::find($r->question->pillar_id);
                $pillarName = $pModel ? $pModel->name : $r->question->pillar_id;
                $max = (float)$r->question->weight;
                $earned = (float)$r->score_awarded;
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
            'company' => [
                'name' => $profile->company_name ?? $user->full_name,
                'overallScore' => $latestAssessment ? (float)$latestAssessment->total_score : 0
            ],
            'pillars' => $pillarStats,
            'progress' => $progress,
            'actions' => $actions
        ]);
    }

}