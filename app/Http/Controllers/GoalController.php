<?php

namespace App\Http\Controllers;

use App\Models\Goal;
use App\Models\SmeProfile;
use App\Models\Assessment;
use App\Models\AssessmentResponse;
use App\Models\Pillar;
use App\Models\FrameworkSetting;
use Illuminate\Http\Request;
use Carbon\Carbon;

class GoalController extends Controller
{
    private function getThresholds(): array
    {
        $settings = FrameworkSetting::where('key', 'framework_config')->first();
        return $settings?->value['thresholds'] ?? FrameworkSetting::where('key', 'thresholds')->first()?->value ?? [
            ['id' => 'investor', 'min' => 80, 'max' => 100, 'label' => 'Investor Ready', 'color' => '#10b981'],
            ['id' => 'near',     'min' => 60, 'max' => 79,  'label' => 'Near Ready',     'color' => '#f59e0b'],
            ['id' => 'early',    'min' => 40, 'max' => 59,  'label' => 'Early Stage',    'color' => '#0d9488'],
            ['id' => 'pre',      'min' => 0,  'max' => 39,  'label' => 'Pre-Investment', 'color' => '#e11d48'],
        ];
    }

    private function getThresholdLabel(float $score, array $thresholds): string
    {
        $sorted = collect($thresholds)->sortByDesc('min')->values();
        foreach ($sorted as $t) {
            $t = (array) $t;
            if ($score >= $t['min']) return $t['label'];
        }
        return 'Pre-Investment';
    }

    private function calcPillarScores(Assessment $assessment, array $thresholds): array
    {
        $pillars   = Pillar::all()->keyBy('id');
        $responses = AssessmentResponse::where('assessment_id', $assessment->id)
            ->with('question')
            ->get();

        $grouped = [];
        foreach ($responses as $r) {
            if (!$r->question) continue;
            $pid = $r->question->pillar_id;
            $grouped[$pid] ??= ['earned' => 0, 'max' => 0];
            $grouped[$pid]['earned'] += (float) $r->score_awarded;
            $grouped[$pid]['max']    += (float) $r->question->weight;
        }

        $result = [];
        foreach ($pillars as $p) {
            $data  = $grouped[$p->id] ?? ['earned' => 0, 'max' => 0];
            $score = $data['max'] > 0 ? round(($data['earned'] / $data['max']) * 100, 1) : 0;
            $result[] = [
                'id'       => $p->id,
                'name'     => $p->name,
                'pillar_name' => $p->name, // for frontend compatibility
                'score'    => $score,
                'weight'   => (float) $p->weight,
                'riskLevel'=> $this->getThresholdLabel($score, $thresholds),
            ];
        }
        return $result;
    }

    public function index(Request $request)
    {
        $user = auth()->user();
        $smeId = $request->query('sme_id');

        if ($user->smeProfile) {
            $goals = Goal::query()->with(['smeProfile', 'smeProfile.user', 'createdBy'])
                ->where('sme_id', $user->smeProfile->id)
                ->latest()
                ->get();
        } elseif ($user->role === 'ADMIN' || $user->role === 'INVESTOR') {
            $goals = Goal::query()->with(['smeProfile', 'smeProfile.user', 'createdBy'])->latest()->get();
        } else {
            return $this->success([], 'No goals found');
        }

        $data = $goals->map(function ($goal) {
            $profile = $goal->smeProfile;
            $u = $profile?->user;
            $creator = $goal->createdBy;
            $creatorRole = $creator?->role ?? 'SME';
            $investorProfile = $creator?->investorProfile;
            
            return array_merge($goal->toArray(), [
                'sme_name' => $profile?->company_name ?? $u?->full_name ?? 'SME User',
                'industry' => $profile?->industry ?? 'N/A',
                'location' => $profile?->address ?? 'N/A',
                'current_score' => (float) ($profile?->readiness_score ?? 0),
                'created_by_role' => strtolower($creatorRole),
                'investor_name' => $creator?->name ?? $creator?->full_name,
                'investor_company' => $investorProfile?->company_name
            ]);
        });

        return $this->success($data, 'Goals retrieved successfully');
    }

    public function show($id)
    {
        $user = auth()->user();
        $query = Goal::with(['smeProfile', 'smeProfile.user']);

        if ($user->role === 'SME') {
            if (!$user->smeProfile) {
                return $this->error('SME profile not found', 404);
            }
            $query->where('sme_id', (string) $user->smeProfile->id);
        }

        $goal = $query->findOrFail($id);
        $profile = $goal->smeProfile;
        $thresholds = $this->getThresholds();

        // Get assessment history
        $assessments = Assessment::where('sme_id', $profile?->id)
            ->where('status', 'Completed')
            ->orderBy('completed_at', 'asc')
            ->get();

        $latestAssessment = $assessments->last();
        $scoreHistory = $assessments->map(fn($a) => [
            'date'  => $a->completed_at->format('Y-m-d'),
            'score' => round((float) $a->total_score, 1),
        ])->values()->toArray();

        $currentPillars = $latestAssessment ? $this->calcPillarScores($latestAssessment, $thresholds) : [];
        $currentScore = $latestAssessment ? (float) $latestAssessment->total_score : (float) ($profile?->readiness_score ?? 0);

        // Calculate expected_score (simple version: linear between start and target)
        $createdAt = $goal->created_at;
        $dueDate = $goal->due_date ? Carbon::parse($goal->due_date) : null;
        $targetScore = (float) $goal->target_score;
        $expectedScore = $currentScore; // default

        if ($dueDate && $dueDate->isFuture() && $targetScore > $currentScore) {
            $totalDays = $createdAt->diffInDays($dueDate) ?: 1;
            $daysPassed = $createdAt->diffInDays(now());
            $ratio = min(1, $daysPassed / $totalDays);
            $expectedScore = round($currentScore + (($targetScore - $currentScore) * $ratio), 1);
        } elseif ($dueDate && $dueDate->isPast()) {
            $expectedScore = $targetScore;
        }

        $result = array_merge($goal->toArray(), [
            'sme_name' => $profile?->company_name ?? $profile?->user?->full_name ?? 'SME User',
            'industry' => $profile?->industry ?? 'N/A',
            'location' => $profile?->address ?? 'N/A',
            'current_score' => $currentScore,
            'expected_score' => $expectedScore,
            'readiness_history' => array_column($scoreHistory, 'score'),
            'score_history' => $scoreHistory,
            'pillars' => $goal->pillar_targets ?: [], // These are the targets
            'goal_pillars' => $goal->pillar_targets ?: [], // Added for frontend compatibility
            'current_pillars' => $currentPillars,
            'sme_profile' => [
                'pillars' => $currentPillars, // used by modal for current state
                'readiness_score' => $currentScore
            ]
        ]);

        return $this->success($result, 'Goal details retrieved successfully');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'sme_id' => 'required_without_all:title|nullable',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'pillar_id' => 'nullable|string',
            'due_date' => 'nullable|date',
            'target_score' => 'nullable|numeric',
            'pillar_targets' => 'nullable|array',
            // Also allow camelCase from frontend
            'pillarTargets' => 'nullable|array',
            'targetScore' => 'nullable|numeric',
            'targetDate' => 'nullable|date',
            'smeId' => 'nullable',
        ]);

        $user = auth()->user();
        
        if ($user->role === 'SME') {
            $smeId = $user->smeProfile ? $user->smeProfile->id : null;
        } else {
            $smeId = $request->sme_id;
        }

        if (!$smeId) {
            return $this->error('Target SME profile not found', 404);
        }

        $goal = Goal::create([
            'sme_id' => $smeId,
            'created_by' => $user->id,
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'pillar_id' => $validated['pillar_id'] ?? null,
            'due_date' => $validated['due_date'] ?? $validated['targetDate'] ?? null,
            'target_score' => $validated['target_score'] ?? $validated['targetScore'] ?? null,
            'pillar_targets' => $validated['pillar_targets'] ?? $validated['pillarTargets'] ?? null,
            'status' => 'Not Started',
            'progress_percentage' => 0
        ]);

        return $this->success($goal, 'Goal created successfully', 201);
    }

    public function update(Request $request, $id)
    {
        $user = auth()->user();
        $query = Goal::query();

        if ($user->role === 'SME') {
            if (!$user->smeProfile) {
                return $this->error('SME profile not found', 404);
            }
            $query->where('sme_id', $user->smeProfile->id);
        }

        $goal = $query->findOrFail($id);

        // Only allow updating if you are the SME who owns the goal, or an Admin/Investor with access
        // For now, let's keep it simple and allow the update if you are authenticated and authorized.

        $validated = $request->validate([
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'status' => 'nullable|string',
            'progress_percentage' => 'nullable|integer|min:0|max:100',
            'due_date' => 'nullable|date',
            'pillar_targets' => 'nullable|array',
        ]);

        $goal->update($validated);
        return $this->success($goal, 'Goal updated successfully');
    }

    public function destroy($id)
    {
        $user = auth()->user();
        $query = Goal::query();

        if ($user->role === 'SME') {
            if (!$user->smeProfile) {
                return $this->error('SME profile not found', 404);
            }
            $query->where('sme_id', $user->smeProfile->id);
        }

        $goal = $query->findOrFail($id);
        $goal->delete();
        return $this->success(null, 'Goal deleted successfully');
    }
}