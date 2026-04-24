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
        // Cache the heavy counting logic for 5 minutes (300 seconds)
        // This stops the database from crashing if admins refresh the page constantly
        $stats = \Illuminate\Support\Facades\Cache::remember('admin_dashboard_stats', 300, function () {
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

            return array_merge($userStats, $programStats);
        });

        return $this->success([
            'stats'       => $stats,
            'recentUsers' => User::latest()->limit(5)->get(), // Leave this un-cached so the recent users list is always live
        ], 'Dashboard statistics retrieved successfully');
    }
}
