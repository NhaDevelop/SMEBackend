<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Program;
use App\Models\ProgramEnrollment;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Traits\ApiResponse;

class ProgramController extends Controller
{
    use ApiResponse;

    public function index()
    {
        $user = auth('api')->user();
        $query = Program::with(['template']);
        
        // If not admin, only show Published programs
        if (!$user || $user->role !== 'ADMIN') {
            $query->whereIn('status', ['Published', 'Active', 'Open']);
        }

        // Optimization: use withCount to get enrollment stats in a single query
        $query->withCount([
            'enrollments as smes_count' => function ($q) { $q->whereNotNull('sme_id'); },
            'enrollments as investors_count' => function ($q) { $q->whereNotNull('investor_id'); }
        ]);

        // Optimization: batch check enrollment for the current user
        $userEnrollments = [];
        if ($user) {
            $profileId = ($user->role === 'SME') ? $user->smeProfile?->id : $user->investorProfile?->id;
            if ($profileId) {
                $userEnrollments = ProgramEnrollment::where($user->role === 'SME' ? 'sme_id' : 'investor_id', $profileId)
                    ->pluck('program_id')
                    ->toArray();
            }
        }

        $programs = $query->get()->map(function ($program) use ($userEnrollments) {
            return $this->formatProgram($program, $userEnrollments);
        });

        $avgScore = $programs->filter(fn($p) => ($p['avgScore'] ?? 0) > 0)->avg('avgScore') ?? 0;

        return $this->success([
            'programs' => $programs,
            'stats' => [
                'total' => $programs->count(),
                'active' => $programs->where('status', 'Published')->count() + $programs->where('status', 'Active')->count(),
                'enrolled' => ProgramEnrollment::distinct('sme_id')->count(),
                'avgScore' => round($avgScore)
            ]
        ], 'Programs retrieved successfully');
    }

    public function store(Request $request)
    {
        $this->mapCamelCase($request);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'template_id' => [
                'nullable',
                'exists:templates,id',
                function ($attribute, $value, $fail) {
            $template = \App\Models\Template::find($value);
            if ($template && $template->status !== 'Active') {
                $fail('The selected template must be active.');
            }
            if (\App\Models\Program::where('template_id', $value)->exists()) {
                $fail('This template is already assigned to another program.');
            }
        },
            ],
            'status' => 'nullable|string|in:Coming Soon,Published,Unpublished,Finished',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'enrollment_deadline' => 'nullable|date',
            'sector' => 'nullable|string',
            'duration' => 'nullable|string',
            'investment_amount' => 'nullable|string',
            'benefits' => 'nullable|array',
            'thresholds' => 'nullable|array',
        ]);

        $program = Program::create(array_merge($validated, [
            'status' => $validated['status'] ?? 'Coming Soon',
            'created_by_user_id' => auth()->id()
        ]));

        return $this->success($this->formatProgram($program->load('template')), 'Program created successfully', 201);
    }

    public function show($idOrSlug)
    {
        $program = Program::with(['template'])
            ->where('id', $idOrSlug)
            ->orWhere('slug', $idOrSlug)
            ->firstOrFail();

        return $this->success($this->formatProgram($program));
    }

    public function update(Request $request, $id)
    {
        $program = Program::findOrFail($id);
        $this->mapCamelCase($request);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'template_id' => [
                'nullable',
                'exists:templates,id',
                function ($attribute, $value, $fail) use ($id, $program) {
                    // If the template is not being changed, skip assignment validation
                    if ($value == $program->template_id) {
                        return;
                    }
                    
                    $template = \App\Models\Template::find($value);
                    if ($template && $template->status !== 'Active') {
                        $fail('The selected template must be active.');
                    }
                    if (\App\Models\Program::where('template_id', $value)->where('id', '!=', $id)->exists()) {
                        $fail('This template is already assigned to another program.');
                    }
                },
            ],
            'status' => 'sometimes|string|in:Coming Soon,Published,Unpublished,Finished',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'enrollment_deadline' => 'nullable|date',
            'sector' => 'nullable|string',
            'duration' => 'nullable|string',
            'investment_amount' => 'nullable|string',
            'benefits' => 'nullable|array',
            'thresholds' => 'nullable|array',
        ]);

        $program->update($validated);

        return $this->success($this->formatProgram($program->load('template')), 'Program updated successfully');
    }

    public function updateStatus(Request $request, $id)
    {
        $program = Program::findOrFail($id);

        $validated = $request->validate([
            'status' => 'required|in:Coming Soon,Published,Unpublished,Finished'
        ]);

        $program->update(['status' => $validated['status']]);

        return $this->success($this->formatProgram($program->load('template')), "Program status updated to {$validated['status']}");
    }

    public function destroy($id)
    {
        $program = Program::findOrFail($id);
        $program->delete();
        return $this->success(null, 'Program deleted successfully');
    }

    private function mapCamelCase(Request $request)
    {
        $mappings = [
            'templateId' => 'template_id',
            'startDate' => 'start_date',
            'endDate' => 'end_date',
            'enrollmentDeadline' => 'enrollment_deadline',
            'investmentAmount' => 'investment_amount',
            'duration' => 'duration',
        ];

        foreach ($mappings as $frontend => $backend) {
            if ($request->has($frontend) && !$request->has($backend)) {
                $request->merge([$backend => $request->$frontend]);
            }
        }
    }

    private function formatProgram($program, $userEnrollments = [])
    {
        $totalSmes = $program->smes_count ?? ProgramEnrollment::where('program_id', $program->id)->whereNotNull('sme_id')->count();
        $totalInvestors = $program->investors_count ?? ProgramEnrollment::where('program_id', $program->id)->whereNotNull('investor_id')->count();
        
        $completedAssessments = \App\Models\Assessment::where('program_id', $program->id)
            ->where('status', 'Completed')
            ->select('sme_id', 'total_score')
            ->get();
            
        $uniqueCompleted = $completedAssessments->unique('sme_id');
        $progress = $totalSmes > 0 ? round(($uniqueCompleted->count() / $totalSmes) * 100) : 0;
        $avgScore = $uniqueCompleted->count() > 0 ? round($uniqueCompleted->avg('total_score')) : 0;

        $isEnrolled = in_array($program->id, $userEnrollments);
        $user = auth('api')->user();
 
        return [
            'id' => $program->id,
            'name' => $program->name,
            'slug' => $program->slug,
            'status' => $program->status,
            'isEnrolled' => $isEnrolled,
            'description' => $program->description,
            'template' => $program->template ? $program->template->name : 'No Template',
            'templateId' => $program->template_id,
            'smesCount' => $totalSmes,
            'investorsCount' => $totalInvestors,
            'avgScore' => $avgScore,
            'progress' => $progress,
            'startDate' => $program->start_date ? $program->start_date->format('Y-m-d') : null,
            'endDate' => $program->end_date ? $program->end_date->format('Y-m-d') : null,
            'enrollmentDeadline' => $program->enrollment_deadline ? $program->enrollment_deadline->format('Y-m-d H:i:s') : null,
            'isEnrollmentClosed' => $program->isEnrollmentClosed(),
            'isAssessmentPeriodOver' => $program->isAssessmentPeriodOver(),
            'isFinished' => $program->isFinished(),
            'isComingSoon' => $program->isComingSoon(),
            'duration' => $program->duration ?: $this->calculateDuration($program),
            'sector' => $program->sector,
            'investmentAmount' => $program->investment_amount,
            'benefits' => $program->benefits,
            'thresholds' => $program->thresholds,
            'createdAt' => $program->created_at ? $program->created_at->format('Y-m-d') : null,
        ];
    }

    /**
     * Admin bulk-enrolls SMEs (by sme_profile.id) — sets status to Accepted.
     */
    public function enrollSmes(Request $request)
    {
        $validated = $request->validate([
            'programId' => 'required|exists:programs,id',
            'smeIds' => 'required|array',
            'smeIds.*' => 'exists:sme_profiles,id'
        ]);

        foreach ($validated['smeIds'] as $smeId) {
            ProgramEnrollment::updateOrCreate(
            ['program_id' => $validated['programId'], 'sme_id' => $smeId],
            ['status' => 'Accepted', 'enrollment_date' => now()]
            );
        }

        return $this->success(null, 'SMEs enrolled successfully');
    }

    /**
     * Admin updates enrollment status (e.g. Approved/Rejected).
     */
    public function updateEnrollmentStatus(Request $request)
    {
        $validated = $request->validate([
            'programId' => 'required|exists:programs,id',
            'smeId' => 'required|exists:sme_profiles,id',
            'status' => 'required|string|in:Accepted,Rejected,Dropped,Enrolled'
        ]);

        $enrollment = ProgramEnrollment::where('program_id', $validated['programId'])
            ->where('sme_id', $validated['smeId'])
            ->firstOrFail();

        $oldStatus = $enrollment->status;
        $enrollment->update(['status' => $validated['status']]);

        // Audit Log
        \App\Models\AuditLog::create([
            'user_id' => auth('api')->id(),
            'action' => 'UPDATE_ENROLLMENT_STATUS',
            'target_entity' => 'ProgramEnrollment',
            'target_id' => $enrollment->id,
            'details' => json_encode([
                'program_id' => $validated['programId'],
                'sme_id' => $validated['smeId'],
                'old_status' => $oldStatus,
                'new_status' => $validated['status']
            ]),
            'ip_address' => $request->ip()
        ]);

        return $this->success(null, "Enrollment status updated to {$validated['status']}");
    }

    private function calculateDuration($program)
    {
        if (!$program->start_date || !$program->end_date) {
            return null;
        }

        $diff = $program->start_date->diff($program->end_date);

        if ($diff->y > 0) {
            return $diff->y . ' ' . Str::plural('Year', $diff->y) . ($diff->m > 0 ? ' ' . $diff->m . ' ' . Str::plural('Month', $diff->m) : '');
        }

        if ($diff->m > 0) {
            return $diff->m . ' ' . Str::plural('Month', $diff->m);
        }

        return $diff->days . ' ' . Str::plural('Day', $diff->days);
    }

    /**
     * POST /api/programs/{id}/apply
     * SME Applies to a program.
     */
    public function apply(Request $request, $id)
    {
        $program = Program::findOrFail($id);
        $smeProfile = auth()->user()->smeProfile;

        if (!$smeProfile) {
            return $this->forbidden('SME profile not found. Complete your profile before applying.');
        }

        // Enforce: Program must not be Coming Soon
        if ($program->isComingSoon()) {
            return $this->error('This program has not started yet. Applications will open on ' . $program->start_date->format('Y-m-d'), 403);
        }

        // Enforce: Program must not be Finished
        if ($program->isFinished()) {
            return $this->error('This program has ended and is no longer accepting applications.', 403);
        }

        // Enforce: Enrollment deadline must not have passed
        if ($program->isEnrollmentClosed()) {
            return $this->error('The enrollment period for this program has closed. No new applications are being accepted.', 403);
        }

        $existing = ProgramEnrollment::where('program_id', $program->id)
            ->where('sme_id', $smeProfile->id)
            ->first();

        if ($existing) {
            return $this->error('You have already Applied/Enrolled in this program', 409);
        }

        $enrollment = ProgramEnrollment::create([
            'program_id'      => $program->id,
            'sme_id'          => $smeProfile->id,
            'status'          => 'Enrolled',
            'enrollment_date' => now()
        ]);

        return $this->success($enrollment, 'Enrolled in program successfully', 201);
    }

    /**
     * GET /api/programs/{id}/participants
     * Unified list of SMEs and Investors enrolled in a program.
     */
    public function participants($id)
    {
        $program = Program::findOrFail($id);
        $user = auth()->user();

        // Authorization logic remains
        if ($user->role === 'SME') {
            $isEnrolled = ProgramEnrollment::where('program_id', $id)
                ->where('sme_id', $user->smeProfile?->id)
                ->exists();
            if (!$isEnrolled) return $this->forbidden('You must be enrolled in this program to view participants.');
        } elseif ($user->role === 'INVESTOR') {
            $isEnrolled = ProgramEnrollment::where('program_id', $id)
                ->where('investor_id', $user->investorProfile?->id)
                ->exists();
            if (!$isEnrolled) return $this->forbidden('You must be enrolled in this program to view participants.');
        }

        $enrollments = ProgramEnrollment::where('program_id', $id)
            ->with(['smeProfile.user', 'investorProfile.user'])
            ->get();

        $participants = $enrollments->map(function ($enr) {
            $profile = null;
            $role = 'SME';
            $name = 'Unknown';
            $userId = null;

            if ($enr->sme_id) {
                $role = 'SME';
                $profile = $enr->smeProfile;
                $name = $profile?->company_name ?? $profile?->user?->full_name;
                $userId = $profile?->user_id;
            } elseif ($enr->investor_id) {
                $role = 'INVESTOR';
                $profile = $enr->investorProfile;
                $name = $profile?->organization_name ?? $profile?->user?->full_name;
                $userId = $profile?->user_id;
            }

            // Standardize status masking to 'Enrolled' for all approved states
            $status = $enr->status;
            if (in_array(strtolower($status), ['accepted', 'approved', 'enrolled', 'active'])) {
                $status = 'Enrolled';
            }

            return [
                'id' => $enr->id,
                'user_id' => $userId,
                'name' => $name,
                'role' => $role,
                'status' => $status,
                'enrolled_at' => $enr->enrollment_date?->format('Y-m-d H:i:s'),
                'industry' => $profile?->industry,
                'profile_id' => $profile?->id,
            ];
        });

        return $this->success($participants, 'Participants retrieved successfully');
    }
}