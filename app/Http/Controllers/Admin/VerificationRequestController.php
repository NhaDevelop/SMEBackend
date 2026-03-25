<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\VerificationRequest;
use Illuminate\Http\Request;

class VerificationRequestController extends Controller
{
    public function index()
    {
        $requests = VerificationRequest::with(['user:id,full_name,email'])
            ->latest()
            ->get()
            ->map(function($req) {
                return [
                    'id' => $req->id,
                    'smeName' => $req->user->sme_profile->company_name ?? $req->user->full_name,
                    'documentType' => $req->document_type,
                    'submissionDate' => $req->created_at->format('Y-m-d'),
                    'status' => $req->status,
                    'evidenceLink' => $req->evidence_link,
                    'notes' => $req->notes
                ];
            });

        return response()->json($requests);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'document_type' => 'required|string',
            'evidence_link' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        $verificationRequest = VerificationRequest::create($validated);
        return response()->json($verificationRequest, 201);
    }

    public function update(Request $request, $id)
    {
        $verificationRequest = VerificationRequest::findOrFail($id);
        
        $validated = $request->validate([
            'status' => 'required|in:Pending,Approved,Rejected',
            'notes' => 'nullable|string'
        ]);

        $verificationRequest->update($validated);
        
        return response()->json([
            'message' => 'Verification request status updated',
            'request' => $verificationRequest
        ]);
    }
}
