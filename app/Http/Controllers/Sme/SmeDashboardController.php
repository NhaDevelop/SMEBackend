<?php

namespace App\Http\Controllers\Sme;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Assessment;
use App\Models\AssessmentResponse;
use App\Models\Pillar;
use App\Models\SmeProfile;
use Illuminate\Http\Request;
use App\Traits\ApiResponse;

use App\Services\AssessmentService;

class SmeDashboardController extends Controller
{
    use ApiResponse;

    protected $assessmentService;

    public function __construct(AssessmentService $assessmentService)
    {
        $this->assessmentService = $assessmentService;
    }

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
    public function index(Request $request)
    {
        $user = auth()->user();
        $user->load('smeProfile');
        if (!$user || !$user->smeProfile) {
            return $this->error('SME profile not found', 404);
        }

        $profile = $user->smeProfile;
        $programId = $request->input('program_id');

        $cacheKey = 'sme_dashboard_' . $profile->id . ($programId ? '_prog_' . $programId : '');

        $payload = \Illuminate\Support\Facades\Cache::remember(
            $cacheKey,
            120,
            fn () => $this->buildSmeDashboardPayload($user, $profile, $programId)
        );

        return $this->success($payload);
    }

    protected function buildSmeDashboardPayload($user, $profile, $programId = null): array
    {

        // 1. Fetch Latest Assessment & Responses (now with program association)
        $assessmentQuery = Assessment::with('program')
            ->where('sme_id', $profile->id)
            ->where('status', 'Completed');
            
        if ($programId) {
            $assessmentQuery->where('program_id', $programId);
        }

        $latestAssessment = $assessmentQuery->latest()->first();

        $thresholds = $this->assessmentService->getThresholds($latestAssessment?->program_id);

        $pillarStats = [];
        if ($latestAssessment) {
            $pillarStats = $this->assessmentService->calculatePillarScores($latestAssessment, $thresholds);
        }
        else {
            // No assessment yet — use cached pillars
            $pillars = \Illuminate\Support\Facades\Cache::remember('pillars_all', 3600, fn() => Pillar::all());
            foreach ($pillars as $p) {
                $pillarStats[] = [
                    'id' => $p->id,
                    'score' => 0,
                    'weight' => (float)$p->weight,
                    'improvementPotential' => 100,
                    'riskLevel' => 'Not Assessed'
                ];
            }
        }

        // Fix #6: Limit to most recent 12 entries — prevents unbounded growth as SME retakes assessments
        $progress = Assessment::where('sme_id', $profile->id)
            ->where('status', 'Completed')
            ->orderBy('completed_at', 'desc')
            ->limit(12)
            ->get()
            ->reverse()
            ->values()
            ->map(function ($a) {
            return [
            'month' => $a->completed_at->format('M'),
            'score' => (float)$a->total_score
            ];
        });

        $actions = [];
        if ($latestAssessment) {
            $actions = $this->assessmentService->calculateTopActions($latestAssessment, 10);
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

        // 3. Goal Stats
        $goals = \App\Models\Goal::where('sme_id', $profile->id)->get();
        $activeGoals = $goals->filter(function($g) {
            return in_array(strtolower($g->status), ['active', 'in progress', 'not started']);
        });
        $achievedGoals = $goals->filter(function($g) {
            return in_array(strtolower($g->status), ['achieved', 'completed']);
        });
        
        $activeGoalsCount = $activeGoals->count();
        $achievedGoalsCount = $achievedGoals->count();
        $avgProgress = $activeGoalsCount > 0 ? (int) $activeGoals->avg('progress_percentage') : 0;

        $primaryDbGoal = $activeGoals->sortByDesc('created_at')->first();
        $primaryGoal = null;
        if ($primaryDbGoal) {
            $now = now();
            $dueDate = $primaryDbGoal->due_date ? \Carbon\Carbon::parse($primaryDbGoal->due_date) : null;
            $overdue = false;
            $daysRemaining = 0;
            if ($dueDate) {
                if ($dueDate->isPast()) {
                    $overdue = true;
                    $daysRemaining = $now->diffInDays($dueDate);
                } else {
                    $daysRemaining = $now->diffInDays($dueDate);
                }
            }
            
            $primaryGoal = [
                'id' => $primaryDbGoal->id,
                'title' => $primaryDbGoal->title,
                'target' => $primaryDbGoal->target_score,
                'progress' => $primaryDbGoal->progress_percentage ?? 0,
                'isOverdue' => $overdue,
                'daysRemaining' => $daysRemaining,
                'focus' => $primaryDbGoal->description,
            ];
        }

        $enrolledProgramRows = \App\Models\ProgramEnrollment::with('program')
            ->where('sme_id', $profile->id)
            ->whereIn('status', ['Enrolled', 'Approved', 'Active'])
            ->get();

        $enrolledPrograms = [];
        foreach ($enrolledProgramRows as $enrollment) {
            if (!is_null($enrollment->program_id)) {
                $enrolledPrograms[] = [
                    'id'   => $enrollment->program_id,
                    'name' => $enrollment->program?->name ?? 'Unknown Program',
                ];
            }
        }

        return [
            'company' => [
                'name' => $profile->company_name ?? $user->full_name,
                'industry' => $profile->industry,
                'overallScore' => $latestAssessment ? (float)$latestAssessment->total_score : 0,
                'risk_level' => $latestAssessment ? $this->assessmentService->getThresholdLabel($latestAssessment->total_score, $thresholds) : 'Not Assessed',
                'lastAssessed' => $latestAssessment ? $latestAssessment->completed_at->format('Y-M-d') : null,
                'updated_at' => $latestAssessment ? $latestAssessment->completed_at->format('Y-m-d H:i:s') : $profile->updated_at->format('Y-m-d H:i:s')
            ],
            'enrolled_programs' => $enrolledPrograms,
            'thresholds' => $thresholds,
            'pillars' => $pillarStats,
            'progress' => $progress,
            'actions' => $actions,
            'primaryGoal' => $primaryGoal,
            'goalsStats' => [
                'active' => $activeGoalsCount,
                'achieved' => $achievedGoalsCount,
                'progress' => $avgProgress
            ]
        ];
    }

}
