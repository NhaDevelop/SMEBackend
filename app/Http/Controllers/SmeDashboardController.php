<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Assessment;
use App\Models\AssessmentResponse;
use App\Models\Pillar;
use App\Models\SmeProfile;
use Illuminate\Http\Request;
use App\Traits\ApiResponse;

class SmeDashboardController extends Controller
{
    use ApiResponse;

    /**
     * GET /api/sme/profile
     * Return the authenticated SME's profile details.
     */
    public function profile()
    {
        $user = auth()->user();
        $user->load('smeProfile');

        if (!$user || !$user->smeProfile) {
            return $this->error('SME profile not found', 404);
        }

        $profile = $user->smeProfile;

        return $this->success([
            'id'                 => $profile->id,
            'name'               => $profile->company_name ?? $user->full_name,
            'company_name'       => $profile->company_name,
            'industry'           => $profile->industry,
            'location'           => $profile->address,
            'address'            => $profile->address,
            'email'              => $user->email,
            'phone'              => $user->phone,
            'score'              => (float)($profile->readiness_score ?? 0),
            'readiness_score'    => (float)($profile->readiness_score ?? 0),
            'registrationNumber' => $profile->registration_number,
            'yearsInBusiness'    => $profile->years_in_business,
            'teamSize'           => $profile->team_size,
            'stage'              => $profile->stage,
            'websiteUrl'         => $profile->website_url,
        ]);
    }

    /**
     * GET /api/sme/dashboard
     * Fetch analytics for the authenticated SME.
     */
    public function index()
    {
        $user = auth()->user();
        $user->load('smeProfile');
        if (!$user || !$user->smeProfile) {
            return $this->error('SME profile not found', 404);
        }

        $profile = $user->smeProfile;

        // 1. Fetch Latest Assessment & Responses
        $latestAssessment = Assessment::where('sme_id', $profile->id)
            ->where('status', 'Completed')
            ->latest()
            ->first();

        $settings = \App\Models\FrameworkSetting::where('key', 'framework_config')->first();
        $thresholds = $settings ? ($settings->value['thresholds'] ?? []) : [
            ['id' => 'investor', 'label' => 'Investment Ready', 'min' => 80, 'max' => 100],
            ['id' => 'near', 'label' => 'Near Ready', 'min' => 60, 'max' => 79],
            ['id' => 'early', 'label' => 'Early Stage', 'min' => 40, 'max' => 59],
            ['id' => 'pre', 'label' => 'Pre-Investment', 'min' => 0, 'max' => 39],
        ];

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
                    'riskLevel' => 'Not Assessed'
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
                    'description' => 'Your current score for this indicator is ' . round($ratio * 100) . '%. Addressing this gap could improve your overall readiness by ' . round($max - $earned, 1) . ' points.',
                    'priority' => $ratio <= 0.2 ? 'high' : ($ratio <= 0.5 ? 'medium' : 'low'),
                    'pillarRisk' => $ratio <= 0.2 ? 'high' : ($ratio <= 0.5 ? 'medium' : 'low'),
                    'pillar' => $pillarName,
                    'impact' => round(($max - $earned), 1),
                    'points' => round(($max - $earned), 1),
                    'status' => 'pending'
                ];
            })->sortByDesc('impact')->values()->take(10);

            $actions = $gaps->toArray();
        } else {
            // 🆕 Onboarding state: no assessment taken yet -> show a single getting-started action
            $actions[] = [
                'id' => 'onboarding_start',
                'title' => 'Get Started: Enroll in a Program & Take Your Assessment',
                'description' => 'You haven\'t completed an assessment yet. Enroll in a program and complete your first investment readiness assessment to unlock personalized recommendations and track your progress.',
                'priority' => 'high',
                'pillar' => 'General',
                'pillarScore' => 0,
                'pillarRisk' => 'high',
                'impact' => 100,
                'status' => 'pending',
                'type' => 'onboarding'
            ];
        }

        return $this->success([
            'company' => [
                'name' => $profile->company_name ?? $user->full_name,
                'industry' => $profile->industry,
                'overallScore' => $latestAssessment ? (float)$latestAssessment->total_score : 0,
                'risk_level' => $latestAssessment ? $this->getRiskLabel($latestAssessment->total_score, $thresholds) : 'Not Assessed',
                'updated_at' => $latestAssessment ? $latestAssessment->completed_at->format('Y-m-d H:i:s') : $profile->updated_at->format('Y-m-d H:i:s')
            ],
            'thresholds' => $thresholds,
            'pillars' => $pillarStats,
            'progress' => $progress,
            'actions' => $actions
        ]);
    }

    private function getRiskLabel($score, $thresholds)
    {
        foreach ($thresholds as $t) {
            if ($score >= $t['min'] && $score <= $t['max']) {
                return $t['label'];
            }
        }
        
        // Fallback
        if ($score >= 80) return 'Investor Ready';
        if ($score >= 60) return 'Near Ready';
        if ($score >= 40) return 'Early Stage';
        return 'Pre-Investment';
    }

    private function calculatePillarScores($assessment, $thresholds)
    {
        $pillars = Pillar::all();
        $responses = AssessmentResponse::where('assessment_id', $assessment->id)
            ->with('question')
            ->get();

        $grouped = [];
        foreach ($responses as $r) {
            if (!$r->question)
                continue;
            $pid = $r->question->pillar_id;
            if (!isset($grouped[$pid])) {
                $grouped[$pid] = ['earned' => 0, 'max' => 0];
            }
            $grouped[$pid]['earned'] += (float)$r->score_awarded;
            $grouped[$pid]['max'] += (float)$r->question->weight;
        }

        $stats = [];
        foreach ($pillars as $p) {
            $data = $grouped[$p->id] ?? ['earned' => 0, 'max' => 0];
            $score = $data['max'] > 0 ? round(($data['earned'] / $data['max']) * 100, 1) : 0;

            $stats[] = [
                'id' => $p->id,
                'name' => $p->name,
                'score' => $score,
                'weight' => (float)$p->weight,
                'riskLevel' => $this->getRiskLabel($score, $thresholds)
            ];
        }

        return $stats;
    }
}