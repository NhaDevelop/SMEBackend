<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Program;
use App\Models\ProgramEnrollment;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ProgramController extends Controller
{
    public function index()
    {
        $programs = Program::with(['template'])->get()->map(function ($program) {
            return $this->formatProgram($program);
        });

        $avgScore = $programs->whereStrict('avgScore', '>', 0)->avg('avgScore') ?? 0;

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
            'sector' => 'nullable|string',
            'duration' => 'nullable|string',
            'investment_amount' => 'nullable|string',
            'benefits' => 'nullable|array',
        ]);

        $program = Program::create(array_merge($validated, [
            'status' => $validated['status'] ?? 'Coming Soon',
            'created_by_user_id' => auth()->id()
        ]));

        return $this->success($this->formatProgram($program->load('template')), 'Program created successfully', 201);
    }

    public function show($id)
    {
        $program = Program::with(['template'])->findOrFail($id);
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
            'status' => 'sometimes|string|in:Coming Soon,Published,Unpublished',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'sector' => 'nullable|string',
            'duration' => 'nullable|string',
            'investment_amount' => 'nullable|string',
            'benefits' => 'nullable|array',
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
            'investmentAmount' => 'investment_amount',
            'duration' => 'duration',
        ];

        foreach ($mappings as $frontend => $backend) {
            if ($request->has($frontend) && !$request->has($backend)) {
                $request->merge([$backend => $request->$frontend]);
            }
        }
    }

    private function formatProgram($program)
    {
        $totalSmes = ProgramEnrollment::where('program_id', $program->id)->whereNotNull('sme_id')->count();
        $totalInvestors = ProgramEnrollment::where('program_id', $program->id)->whereNotNull('investor_id')->count();
        $progress = 0;
        $avgScore = 0;

        if ($totalSmes > 0 && $program->template_id) {
            $smeIds = ProgramEnrollment::where('program_id', $program->id)->whereNotNull('sme_id')->pluck('sme_id');
            $completedAssessments = \App\Models\Assessment::where('template_id', $program->template_id)
                ->whereIn('sme_id', $smeIds)
                ->where('status', 'Completed')
                ->get();

            $completedCount = $completedAssessments->count();
            $progress = round(($completedCount / $totalSmes) * 100);
            
            // Use direct DB average for more reliability
            $avgScore = \App\Models\Assessment::where('template_id', $program->template_id)
                ->whereIn('sme_id', $smeIds)
                ->where('status', 'Completed')
                ->avg('total_score') ?: 0;
            
            $avgScore = round($avgScore, 1);
        }
 
        return [
            'id' => $program->id,
            'name' => $program->name,
            'status' => $program->status,
            'description' => $program->description,
            'template' => $program->template ? $program->template->name : 'No Template',
            'templateId' => $program->template_id,
            'smesCount' => $totalSmes,
            'investorsCount' => $totalInvestors,
            'avgScore' => $avgScore,
            'progress' => $progress,
            'startDate' => $program->start_date ? $program->start_date->format('Y-m-d') : null,
            'endDate' => $program->end_date ? $program->end_date->format('Y-m-d') : null,
            'duration' => $program->duration ?: $this->calculateDuration($program),
            'sector' => $program->sector,
            'investmentAmount' => $program->investment_amount,
            'benefits' => $program->benefits,
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
     * SME self-applies to a program — creates an Applied enrollment.
     */
    public function apply(Request $request, $id)
    {
        $program = Program::findOrFail($id);
        $smeProfile = auth()->user()->smeProfile;

        if (!$smeProfile) {
            return response()->json(['error' => 'SME profile not found'], 403);
        }

        $existing = ProgramEnrollment::where('program_id', $program->id)
            ->where('sme_id', $smeProfile->id)
            ->first();

        if ($existing) {
            return response()->json([
                'message' => 'You have already applied to this program',
                'status' => $existing->status
            ], 409);
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
     * Admin updates enrollment status (e.g. Approved/Rejected).
     */
    public function updateEnrollmentStatus(Request $request)
    {
        $validated = $request->validate([
            'programId' => 'required|exists:programs,id',
            'smeId' => 'required|exists:sme_profiles,id',
            'status' => 'required|string|in:Accepted,Rejected,Dropped,Enrolled' // Removed 'Applied' - auto enrollment only
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
     * GET /api/programs/{id}/participants
     * Unified list of SMEs and Investors enrolled in a program.
     */
    public function participants($id)
    {
        $program = Program::findOrFail($id);
        $user = auth()->user();

        // Authorization: SMEs and Investors must be enrolled to see participants
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
            $role = 'Unknown';
            $name = 'Unknown';
            $userId = null;

            if ($enr->sme_id) {
                $role = 'SME';
                $profile = $enr->smeProfile;
                $name = $profile?->company_name ?? $profile?->user?->full_name;
                $userId = $profile?->user_id;
            } elseif ($enr->investor_id) {
                $role = 'Investor';
                $profile = $enr->investorProfile;
                $name = $profile?->organization_name ?? $profile?->user?->full_name;
                $userId = $profile?->user_id;
            }

            return [
                'id' => $enr->id,
                'user_id' => $userId,
                'name' => $name,
                'role' => $role,
                'status' => $enr->status,
                'enrolled_at' => $enr->enrollment_date?->format('Y-m-d H:i:s'),
                'industry' => $profile?->industry,
                'profile_id' => $profile?->id,
            ];
        });

        return $this->success($participants, 'Participants retrieved successfully');
    }
}