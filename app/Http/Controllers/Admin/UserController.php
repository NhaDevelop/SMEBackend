<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\SmeProfile;
use App\Models\ProgramEnrollment;
use App\Models\Program;
use App\Services\AssessmentService;
use Illuminate\Support\Facades\Mail;
use App\Mail\UserApprovedMail;
use App\Mail\UserRejectedMail;

class UserController extends Controller
{
    protected $assessmentService;

    public function __construct(AssessmentService $assessmentService)
    {
        $this->assessmentService = $assessmentService;
    }
    public function fetchPendingUsers(Request $request)
    {
        $search = $request->query('search');
        $role = $request->query('role');

        // Only show users who have verified their email (PENDING = verified, awaiting admin)
        // PENDING_VERIFICATION users are excluded until they click their email link
        $query = User::where('status', 'PENDING')->with(['smeProfile', 'investorProfile']);

        if ($role && strtolower($role) !== 'all') {
            $query->where('role', strtoupper($role));
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhereHas('smeProfile', function ($q2) use ($search) {
                      $q2->where('company_name', 'like', "%{$search}%");
                  })
                  ->orWhereHas('investorProfile', function ($q2) use ($search) {
                      $q2->where('organization_name', 'like', "%{$search}%");
                  });
            });
        }

        $paginator = $query->latest()->paginate(15);
        $responseArray = $paginator->toArray();
        $responseArray['stats'] = [
            'total'     => User::count(),
            'pending'   => User::where('status', 'PENDING')->count(),
            'smes'      => User::where('role', 'SME')->count(),
            'investors' => User::where('role', 'INVESTOR')->count(),
            'admins'    => User::where('role', 'ADMIN')->count(),
        ];
        return $this->success($responseArray, 'Pending users retrieved successfully');
    }

    public function getApprovedUsers(Request $request)
    {
        $search = $request->query('search');
        $role = $request->query('role');

        $query = User::where('status', 'ACTIVE')->with(['smeProfile', 'investorProfile']);

        if ($role && strtolower($role) !== 'all') {
            $query->where('role', strtoupper($role));
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhereHas('smeProfile', function ($q2) use ($search) {
                      $q2->where('company_name', 'like', "%{$search}%");
                  })
                  ->orWhereHas('investorProfile', function ($q2) use ($search) {
                      $q2->where('organization_name', 'like', "%{$search}%");
                  });
            });
        }

        // Updated to use server-side pagination instead of fetching everything at once
        $paginator = $query->latest()->paginate(15);
        $responseArray = $paginator->toArray();
        $responseArray['stats'] = [
            'total' => User::count(),
            'pending' => User::where('status', 'PENDING')->count(),
            'smes' => User::where('role', 'SME')->count(),
            'investors' => User::where('role', 'INVESTOR')->count(),
            'admins' => User::where('role', 'ADMIN')->count(),
        ];
        return $this->success($responseArray, 'Approved users retrieved successfully');
    }

    public function getSmesData(Request $request)
    {
        $programId = $request->query('program_id');
        $templateId = null;

        if ($programId) {
            $program = \App\Models\Program::find($programId);
            if ($program) {
                $templateId = $program->template_id;
            }
        }


        $query = User::where('role', 'SME')->where('status', 'ACTIVE')->with(['smeProfile.enrollments', 'smeProfile.assessments.responses.question']);

        if ($programId) {
            $query->whereHas('smeProfile.enrollments', function ($q) use ($programId) {
                $q->where('program_id', $programId);
            });
        }

        $thresholds = $this->assessmentService->getThresholds($programId);

        $smes = $query->get()
            ->map(function ($user) use ($templateId, $thresholds, $programId) {
                $profile = $user->smeProfile;
                $enrollments = $profile ? $profile->enrollments : collect([]);

                // Strict Score Logic: Filter by template if provided, else get latest overall
                $assessmentsQuery = $profile ? $profile->assessments : collect([]);

                if ($templateId) {
                    $latestAssessment = $assessmentsQuery->where('template_id', $templateId)
                        ->sortByDesc('completed_at')
                        ->first();
                } else {
                    $latestAssessment = $assessmentsQuery->sortByDesc('completed_at')->first();
                }

                $actualScore = $latestAssessment ? (float) $latestAssessment->total_score : 0;
                $t = $this->assessmentService->getThresholds($latestAssessment?->program_id ?? $programId);
                $riskLabel = $latestAssessment ? $this->assessmentService->getThresholdLabel($actualScore, $t) : 'Not Assessed';

                return [
                    'id' => $user->id,
                    'name' => $profile->company_name ?? $user->full_name,
                    'user_name' => $user->full_name,
                    'industry' => $profile->industry ?? 'N/A',
                    'location' => $profile->address ?? 'N/A',
                    'lastAssessed' => $latestAssessment ? $latestAssessment->completed_at->format('Y-m-d') : 'Never',
                    'riskLevel' => $riskLabel,
                    'readinessStatus' => $latestAssessment ? ($profile->stage ?? 'In Analysis') : 'Needs Assessment',
                    'score' => $actualScore,
                    'pillars' => $latestAssessment ? $this->assessmentService->calculatePillarScores($latestAssessment, $t) : [],
                    'growthPotential' => 0,
                    'programIds' => $enrollments->pluck('program_id')->toArray(),
                    'programEnrollments' => $enrollments->map(function ($e) {
                        return [
                            'programId' => $e->program_id,
                            'status' => $e->status,
                            'appliedAt' => $e->created_at->format('Y-m-d'),
                        ];
                    })->toArray(),
                ];
            });
        return $this->success($smes, 'SME data retrieved successfully');
    }

    public function show($id)
    {
        $user = User::with(['smeProfile', 'investorProfile'])->findOrFail($id);
        return $this->success($user);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'full_name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'phone' => 'nullable|string|max:255',
            'password' => 'required|string|min:8',
            'role' => 'required|in:SME,INVESTOR,ADMIN',
            'status' => 'required|in:PENDING,ACTIVE,REJECTED',
            // Profile fields
            'company_name' => 'nullable|string|max:255',
            'organization_name' => 'nullable|string|max:255',
            'registration_number' => 'nullable|string|max:255',
            'industry' => 'nullable|string|max:255',
            'stage' => 'nullable|string|max:255',
            'years_in_business' => 'nullable|string|max:255',
            'team_size' => 'nullable|string|max:255',
            'address' => 'nullable|string',
            'website' => 'nullable|string',
            'website_url' => 'nullable|string',
            'investor_type' => 'nullable|string',
            'min_ticket_size' => 'nullable|numeric',
            'max_ticket_size' => 'nullable|numeric',
        ]);

        $user = User::create([
            'full_name' => $validated['full_name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'password' => \Illuminate\Support\Facades\Hash::make($validated['password']),
            'role' => $validated['role'],
            'status' => $validated['status'],
        ]);

        // Create the profile with details based on the role
        if ($user->role === 'SME') {
            $user->smeProfile()->create([
                'company_name' => $validated['company_name'] ?? null,
                'registration_number' => $validated['registration_number'] ?? null,
                'industry' => $validated['industry'] ?? null,
                'stage' => $validated['stage'] ?? null,
                'years_in_business' => $validated['years_in_business'] ?? null,
                'team_size' => $validated['team_size'] ?? null,
                'address' => $validated['address'] ?? null,
                'website_url' => $validated['website'] ?? $validated['website_url'] ?? null,
            ]);
        } elseif ($user->role === 'INVESTOR') {
            $user->investorProfile()->create([
                'organization_name' => $validated['organization_name'] ?? $validated['company_name'] ?? null,
                'registration_number' => $validated['registration_number'] ?? null,
                'investor_type' => $validated['investor_type'] ?? null,
                'industry' => $validated['industry'] ?? null,
                'years_in_business' => $validated['years_in_business'] ?? null,
                'team_size' => $validated['team_size'] ?? null,
                'address' => $validated['address'] ?? null,
                'min_ticket_size' => $validated['min_ticket_size'] ?? null,
                'max_ticket_size' => $validated['max_ticket_size'] ?? null,
                'website_url' => $validated['website'] ?? $validated['website_url'] ?? null,
            ]);
        }

        \App\Models\AuditLog::create([
            'user_id' => auth('sanctum')->id(),
            'action' => 'MANUAL_USER_CREATE',
            'target_entity' => 'User',
            'target_id' => $user->id,
            'details' => json_encode(['role' => $user->role, 'email' => $user->email, 'info' => 'Detailed creation']),
            'ip_address' => $request->ip()
        ]);

        return $this->success($user->load(['smeProfile', 'investorProfile']), 'User created with profile successfully', 201);
    }

    public function updateStatus(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $request->validate([
            'action' => 'required|in:approve,reject'
        ]);

        $status = $request->action === 'approve' ? 'ACTIVE' : 'REJECTED';
        $user->update(['status' => $status]);

        \App\Models\AuditLog::create([
            'user_id' => auth('sanctum')->id(),
            'action' => 'UPDATE_STATUS',
            'target_entity' => 'User',
            'target_id' => $user->id,
            'details' => json_encode(['old_status' => 'PENDING', 'new_status' => $status]),
            'ip_address' => $request->ip()
        ]);

        if ($status === 'ACTIVE') {
            try {
                Mail::to($user->email)->send(new UserApprovedMail($user));
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error('Failed to send approval email to ' . $user->email . ': ' . $e->getMessage());
            }
        } elseif ($status === 'REJECTED') {
            try {
                Mail::to($user->email)->send(new UserRejectedMail($user));
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error('Failed to send rejection email to ' . $user->email . ': ' . $e->getMessage());
            }
        }

        return $this->success($user, 'Status updated successfully');
    }

    public function updateRole(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $request->validate([
            'role' => 'required|in:SME,INVESTOR,ADMIN'
        ]);

        $user->update(['role' => $request->role]);
        return $this->success($user, 'Role updated successfully');
    }

    public function resetPassword(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $request->validate([
            'password' => 'required|string|min:8'
        ]);

        $user->update([
            'password' => \Illuminate\Support\Facades\Hash::make($request->password)
        ]);

        \App\Models\AuditLog::create([
            'user_id' => auth('sanctum')->id(),
            'action' => 'RESET_PASSWORD',
            'target_entity' => 'User',
            'target_id' => $user->id,
            'details' => json_encode(['info' => 'Admin manually reset password']),
            'ip_address' => $request->ip()
        ]);

        return $this->success(null, 'User password updated successfully');
    }

    public function destroy($id)
    {
        $user = User::findOrFail($id);
        $user->delete();
        return $this->success(null, 'User deleted successfully');
    }

    public function downloadRegistrationDocument($id)
    {
        $user = User::with(['smeProfile', 'investorProfile'])->findOrFail($id);
        
        $document = null;
        if ($user->role === 'SME' && $user->smeProfile) {
            $document = $user->smeProfile->registration_document;
        } elseif ($user->role === 'INVESTOR' && $user->investorProfile) {
            $document = $user->investorProfile->registration_document;
        }

        if (!$document) {
            return $this->error('No registration document found for this user', 404);
        }

        $path = storage_path('app/private/' . $document);
        
        if (!file_exists($path)) {
            $path = storage_path('app/' . $document);
        }

        if (!file_exists($path)) {
            $legacyPath = storage_path('app/public/' . $document);
            if (file_exists($legacyPath)) {
                $path = $legacyPath;
            } else {
                return $this->error('Registration document file not found on disk', 404);
            }
        }

        return response()->file($path);
    }

    /**
     * GET /api/admin/smes/{id}/programs
     * Returns the list of programs a specific SME is enrolled in.
     * Used to populate the Program filter in the Generate Custom Report UI.
     */
    public function smePrograms($id)
    {
        $sme = SmeProfile::findOrFail($id);

        $programs = ProgramEnrollment::where('sme_id', $sme->id)
            ->whereNotNull('program_id')
            ->with('program')
            ->get()
            ->filter(fn($e) => $e->program !== null)
            ->map(fn($e) => [
                'id'   => $e->program->id,
                'name' => $e->program->name,
            ])
            ->values();

        return $this->success($programs, 'SME programs retrieved successfully');
    }
}
