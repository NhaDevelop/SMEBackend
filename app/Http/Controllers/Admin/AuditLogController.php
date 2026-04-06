<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\AuditLog;

class AuditLogController extends Controller
{
    /**
     * Display a listing of the audit logs.
     */
    public function index()
    {
        // Load the logs with the user who performed the action, ordered by newest first
        $logs = AuditLog::with('user:id,full_name,email')
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return $this->success($logs, 'Audit logs retrieved successfully');
    }

    /**
     * Display the specified audit log.
     */
    public function show($id)
    {
        $log = AuditLog::with('user:id,full_name,email')->findOrFail($id);
        return $this->success($log, 'Audit log retrieved successfully');
    }
}
