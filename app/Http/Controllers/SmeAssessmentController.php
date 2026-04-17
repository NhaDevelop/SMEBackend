<?php

namespace App\Http\Controllers;

use App\Models\Program;
use App\Models\ProgramEnrollment;
use App\Models\Template;
use App\Models\Question;
use App\Models\Pillar;
use Illuminate\Http\Request;
use App\Traits\ApiResponse;

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
        })->with(['template'])->get()->map(function (\App\Models\Program $program) use ($user) {
            $enrollment = ProgramEnrollment::where('program_id', $program->id)
                ->where('sme_id', $user->smeProfile->id)
                ->first();

            $totalSmes = ProgramEnrollment::where('program_id', $program->id)->whereNotNull('sme_id')->count();
            $totalInvestors = ProgramEnrollment::where('program_id', $program->id)->whereNotNull('investor_id')->count();
            $progress = 0;
            $avgScore = 0;

            if ($totalSmes > 0 && $program->template_id) {
                $smeIds = ProgramEnrollment::where('program_id', $program->id)->whereNotNull('sme_id')->pluck('sme_id');
                // Get the latest completed assessment per SME to prevent multi-assessment stat overflow
                $completedAssessments = \App\Models\Assessment::where('template_id', $program->template_id)
                    ->whereIn('sme_id', $smeIds)
                    ->where('status', 'Completed')
                    ->latest('completed_at')
                    ->get()
                    ->unique('sme_id');

                $completedCount = $completedAssessments->count();
                $progress = round(($completedCount / $totalSmes) * 100);
                // Clamp mathematically to prevent edge-case 100%+ errors just in case
                $progress = min(100, max(0, $progress));
                $avgScore = round($completedCount > 0 ? $completedAssessments->avg('total_score') : 0);
            }

            // Normalize enrollment status
            $eStatus = $enrollment ? $enrollment->status : 'None';
            if (in_array(strtolower($eStatus), ['accepted', 'approved', 'enrolled', 'active'])) {
                $eStatus = 'Enrolled';
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
                'enrollmentStatus' => $eStatus,
                'progress' => $progress,
                'avgScore' => $avgScore,
                'smesCount' => $totalSmes,
                'investorsCount' => $totalInvestors,
                'isEnrollmentClosed' => $program->isEnrollmentClosed(),
                'isAssessmentPeriodOver' => $program->isAssessmentPeriodOver(),
                'isFinished' => $program->isFinished(),
                'isComingSoon' => $program->isComingSoon(),
                'enrollmentDeadline' => $program->enrollment_deadline ? $program->enrollment_deadline->format('Y-m-d H:i:s') : null,
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
            ->get()
            ->map(function ($template) {
                return [
                    'id' => $template->id,
                    'name' => $template->name,
                    'description' => $template->description,
                    'version' => $template->version,
                    'pillarCount' => \App\Models\Pillar::count(),
                ];
            });

        return $this->success($templates, 'Templates retrieved successfully');
    }

    /**
     * Get questions for a specific template.
     */
    public function questions(Request $request)
    {
        $templateId = $request->query('template_id');
        if (!$templateId) {
            return $this->error('Template ID is required', 400);
        }

        $template = Template::find($templateId);
        if (!$template) {
            return $this->error('Template not found', 404);
        }

        // --- NEW: Security Check — is the program associated with this template finished? ---
        $user = auth()->user();
        if ($user && $user->smeProfile) {
            // Find programs using this template that the user is enrolled in
            $programs = Program::where('template_id', $templateId)
                ->whereHas('enrollments', function ($query) use ($user) {
                    $query->where('sme_id', $user->smeProfile->id);
                })
                ->get();

            if ($programs->count() > 0) {
                // If ALL programs associated with this template (where the user is enrolled) are finished, block access.
                $allFinished = $programs->every(function ($program) {
                    return $program->isFinished();
                });

                if ($allFinished) {
                    return $this->error('This assessment is no longer available because the associated program has ended.', 403);
                }
            }
        }
        // --- END SECURITY CHECK ---

        // Return a flat list of questions as expected by the frontend
        $questions = Question::where('template_id', $templateId)
            ->orderBy('pillar_id')
            ->orderBy('id')
            ->get();

        return $this->success($questions, 'Questions retrieved successfully');
    }

    /**
     * Get basic framework settings (pillars, goals structure).
     */
    public function frameworkSettings()
    {
        $pillars = \App\Models\Pillar::all();
        return $this->success($pillars, 'Framework settings retrieved successfully');
    }

    /**
     * Get available sectors.
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

        $programs = Program::whereIn('status', ['Published', 'Open'])
            ->with(['template'])
            ->get()
            ->map(function ($program) use ($smeProfile) {
                $enrollment = $smeProfile ? ProgramEnrollment::where('program_id', $program->id)
                    ->where('sme_id', $smeProfile->id)
                    ->first() : null;

                $totalSmes = ProgramEnrollment::where('program_id', $program->id)->whereNotNull('sme_id')->count();
                $totalInvestors = ProgramEnrollment::where('program_id', $program->id)->whereNotNull('investor_id')->count();
                $progress = 0;
                $avgScore = 0;

                if ($totalSmes > 0 && $program->template_id) {
                    $smeIds = ProgramEnrollment::where('program_id', $program->id)->whereNotNull('sme_id')->pluck('sme_id');
                    // Get the latest completed assessment per SME to prevent multi-assessment stat overflow
                    $completedAssessments = \App\Models\Assessment::where('template_id', $program->template_id)
                        ->whereIn('sme_id', $smeIds)
                        ->where('status', 'Completed')
                        ->latest('completed_at')
                        ->get()
                        ->unique('sme_id');

                    $completedCount = $completedAssessments->count();
                    $progress = round(($completedCount / $totalSmes) * 100);
                    // Clamp mathematically
                    $progress = min(100, max(0, $progress));
                    $avgScore = round($completedCount > 0 ? $completedAssessments->avg('total_score') : 0);
                }

                // Normalize enrollment status
                $eStatus = $enrollment ? $enrollment->status : 'None';
                if (in_array(strtolower($eStatus), ['accepted', 'approved', 'enrolled', 'active'])) {
                    $eStatus = 'Enrolled';
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
                    'enrollmentStatus' => $eStatus,
                    'progress' => $progress,
                    'avgScore' => $avgScore,
                    'smesCount' => $totalSmes,
                    'investorsCount' => $totalInvestors,
                    'isEnrollmentClosed' => $program->isEnrollmentClosed(),
                    'isAssessmentPeriodOver' => $program->isAssessmentPeriodOver(),
                    'isFinished' => $program->isFinished(),
                    'isComingSoon' => $program->isComingSoon(),
                    'enrollmentDeadline' => $program->enrollment_deadline ? $program->enrollment_deadline->format('Y-m-d H:i:s') : null,
                ];
            });

        return $this->success($programs, 'Programs retrieved successfully');
    }

    public function participants($id)
    {
        $user = auth()->user();
        $smeProfile = $user ? $user->smeProfile : null;

        // Check enrollment
        $isEnrolled = $smeProfile ? ProgramEnrollment::where('program_id', $id)
            ->where('sme_id', $smeProfile->id)
            ->exists() : false;

        $enrollments = ProgramEnrollment::where('program_id', $id)
            ->with(['smeProfile', 'investorProfile'])
            ->get()
            ->map(function ($e) use ($isEnrolled) {
                $name = 'Participant';
                $role = 'SME';
                $industry = null;

                if ($e->investorProfile) {
                    $name = $isEnrolled ? ($e->investorProfile->organization_name ?? 'Investor') : 'Investor';
                    $role = 'INVESTOR';
                    $industry = $e->investorProfile->industry;
                } elseif ($e->smeProfile) {
                    $name = $isEnrolled ? ($e->smeProfile->company_name ?? 'SME') : 'SME';
                    $role = 'SME';
                    $industry = $e->smeProfile->industry;
                }

                // Mask status for consistency as requested
                $status = $e->status;
                if (in_array(strtolower($status), ['accepted', 'approved', 'enrolled', 'active'])) {
                    $status = 'Enrolled';
                }

                return [
                    'id' => $e->id,
                    'name' => $name,
                    'role' => $role,
                    'industry' => $industry,
                    'status' => $status,
                    'enrolled_at' => $e->created_at,
                ];
            });

        return $this->success($enrollments, 'Participants retrieved successfully');
    }
}