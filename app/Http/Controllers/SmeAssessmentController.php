<?php

namespace App\Http\Controllers;

use App\Models\Program;
use App\Models\Template;
use App\Models\Question;
use App\Models\Pillar;
use App\Models\SmeProfile;
use App\Models\ProgramEnrollment;
use App\Models\Assessment;
use Illuminate\Http\Request;
use App\Traits\ApiResponse;
use Illuminate\Support\Facades\Log;

class SmeAssessmentController extends Controller
{
    use ApiResponse;

    public function enrolledPrograms()
    {
        $user = auth()->user();
        $user->load('smeProfile');
        if (!$user || !$user->smeProfile) {
            return $this->error('SME profile not found', 404);
        }

        $programs = Program::whereHas('enrollments', function ($query) use ($user) {
            $query->where('sme_id', $user->smeProfile->id);
        })->with(['template'])->get()->map(function ($program) use ($user) {
            $enrollment = ProgramEnrollment::where('program_id', $program->id)
                ->where('sme_id', $user->smeProfile->id)
                ->first();

            $totalSmes = ProgramEnrollment::where('program_id', $program->id)->count();
            $progress = 0;
            $avgScore = 0;

            if ($totalSmes > 0 && $program->template_id) {
                $smeIds = ProgramEnrollment::where('program_id', $program->id)->pluck('sme_id');
                $completedAssessments = \App\Models\Assessment::where('template_id', $program->template_id)
                    ->whereIn('sme_id', $smeIds)
                    ->where('status', 'Completed')
                    ->get();

                $completedCount = $completedAssessments->count();
                $progress = round(($completedCount / $totalSmes) * 100);
                $avgScore = round($completedCount > 0 ? $completedAssessments->avg('total_score') : 0);
            }

            return [
            'id' => $program->id,
            'name' => $program->name,
            'description' => $program->description,
            'status' => $program->status,
            'sector' => $program->sector,
            'investment_amount' => $program->investment_amount,
            'benefits' => $program->benefits,
            'startDate' => $program->start_date ? $program->start_date->format('Y-m-d') : null,
            'endDate' => $program->end_date ? $program->end_date->format('Y-m-d') : null,
            'templateName' => $program->template ? $program->template->name : null,
            'templateId' => $program->template_id,
            'enrollmentStatus' => $enrollment ? $enrollment->status : 'None',
            'progress' => $progress,
            'avgScore' => $avgScore,
            'smesCount' => $totalSmes,
            ];
        });

        return $this->success($programs, 'Enrolled programs retrieved successfully');
    }

    /**
     * Get active assessment templates relevant to the SME.
     */
    public function templates()
    {
        $templates = Template::where('status', 'Active')
            ->withCount(['questions'])
            ->get()
            ->map(function ($t) {
            return [
            'id' => $t->id,
            'name' => $t->name,
            'version' => $t->version,
            'industry' => $t->industry,
            'description' => $t->description,
            'questionCount' => $t->questions_count,
            'pillarCount' => Pillar::count(), // Simplified for now
            ];
        });

        return $this->success($templates, 'Templates retrieved successfully');
    }

    /**
     * Get questions for a specific template (or all active if needed).
     */
    public function questions(Request $request)
    {
        $query = Question::query();

        if ($request->has('template_id')) {
            $query->where('template_id', $request->template_id);
        }

        $questions = $query->get();
        return $this->success($questions, 'Questions retrieved successfully');
    }

    /**
     * Get framework settings (pillars) for the SME dashboard.
     */
    public function frameworkSettings()
    {
        $settings = \App\Models\FrameworkSetting::where('key', 'framework_config')->first();
        
        if ($settings) {
            return $this->success($settings->value, 'Framework settings retrieved successfully');
        }

        $pillars = Pillar::all()->map(function ($p) {
            return [
            'id' => $p->id,
            'name' => $p->name,
            'weight' => (float)$p->weight,
            ];
        });

        // Provide default thresholds if none are saved in DB
        $thresholds = [
            ['id' => 'investor', 'label' => 'Investment Ready', 'min' => 80, 'max' => 100, 'colorBg' => 'bg-emerald-500'],
            ['id' => 'near', 'label' => 'Near Ready', 'min' => 60, 'max' => 79, 'colorBg' => 'bg-amber-500'],
            ['id' => 'early', 'label' => 'Early Stage', 'min' => 40, 'max' => 59, 'colorBg' => 'bg-teal-500'],
            ['id' => 'pre', 'label' => 'Pre-Investment', 'min' => 0, 'max' => 39, 'colorBg' => 'bg-red-500'],
        ];

        return $this->success([
            'pillars' => $pillars,
            'thresholds' => $thresholds
        ], 'Framework settings retrieved successfully');
    }

    /**
     * Get sectors (industries) for the SME profile settings.
     */
    public function sectors()
    {
        return $this->success(\App\Models\Sector::all(), 'Sectors retrieved successfully');
    }

    /**
     * Get all published programs with enrollment status for the SME.
     */
    public function programs()
    {
        $user = auth()->user();
        $smeProfile = $user ? $user->smeProfile : null;

        $programs = Program::where('status', 'Published')
            ->with(['template'])
            ->get()
            ->map(function ($program) use ($smeProfile) {
            $enrollment = $smeProfile ? ProgramEnrollment::where('program_id', $program->id)
                ->where('sme_id', $smeProfile->id)
                ->first() : null;

            $totalSmes = ProgramEnrollment::where('program_id', $program->id)->count();
            $progress = 0;
            $avgScore = 0;

            if ($totalSmes > 0 && $program->template_id) {
                $smeIds = ProgramEnrollment::where('program_id', $program->id)->pluck('sme_id');
                $completedAssessments = \App\Models\Assessment::where('template_id', $program->template_id)
                    ->whereIn('sme_id', $smeIds)
                    ->where('status', 'Completed')
                    ->get();

                $completedCount = $completedAssessments->count();
                $progress = round(($completedCount / $totalSmes) * 100);
                $avgScore = round($completedCount > 0 ? $completedAssessments->avg('total_score') : 0);
            }

            return [
            'id' => $program->id,
            'name' => $program->name,
            'description' => $program->description,
            'status' => $program->status,
            'sector' => $program->sector,
            'investment_amount' => $program->investment_amount,
            'benefits' => $program->benefits,
            'startDate' => $program->start_date ? $program->start_date->format('Y-m-d') : null,
            'endDate' => $program->end_date ? $program->end_date->format('Y-m-d') : null,
            'templateName' => $program->template ? $program->template->name : null,
            'templateId' => $program->template_id,
            'enrollmentStatus' => $enrollment ? $enrollment->status : 'None',
            'progress' => $progress,
            'avgScore' => $avgScore,
            'smesCount' => $totalSmes,
            ];
        });

        return $this->success($programs, 'Programs retrieved successfully');
    }
}