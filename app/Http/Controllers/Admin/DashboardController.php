<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Program;
use App\Models\SmeProfile;
use App\Models\InvestorProfile;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index()
    {
        $userStats = [
            'totalUsers' => User::count(),
            'pendingUsers' => User::where('status', 'PENDING')->count(),
            'totalSMEs' => User::where('role', 'SME')->count(),
            'totalInvestors' => User::where('role', 'INVESTOR')->count(),
            'totalAdmins' => User::where('role', 'ADMIN')->count(),
        ];

        $programStats = [
            'totalPrograms' => Program::count(),
            'activePrograms' => Program::where('status', 'Active')->count(),
            'enrolledSMEs' => \App\Models\ProgramEnrollment::distinct('sme_id')->count(),
            'completedAssessments' => \App\Models\Assessment::where('status', 'COMPLETED')->whereMonth('completed_at', now()->month)->count(),
            'inProgressAssessments' => \App\Models\Assessment::where('status', 'IN_PROGRESS')->count(),
            'avgScore' => \App\Models\Assessment::where('status', 'COMPLETED')->avg('total_score') ?? 0,
        ];

        return $this->success([
            'stats'       => array_merge($userStats, $programStats),
            'recentUsers' => User::latest()->limit(5)->get(),
        ], 'Dashboard statistics retrieved successfully');
    }
}
