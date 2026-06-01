<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use App\Jobs\SendTelegramRegistrationAlertJob;

class EmailVerificationController extends Controller
{
    /**
     * Verify the user's email address via signed URL.
     * GET /api/auth/email/verify/{id}/{hash}
     */
    public function verify(Request $request, $id, $hash)
    {
        $frontendUrl = config('app.frontend_url', 'http://localhost:3000');

        // Validate the signed URL
        if (!URL::hasValidSignature($request)) {
            return redirect($frontendUrl . '/email-verified?status=expired');
        }

        $user = User::findOrFail($id);

        // Check hash matches the user's email
        if (!hash_equals(sha1($user->email), $hash)) {
            return redirect($frontendUrl . '/email-verified?status=invalid');
        }

        // Already verified
        if ($user->email_verified_at) {
            return redirect($frontendUrl . '/email-verified?status=already_verified');
        }

        // Mark as verified and move to admin queue
        $user->update([
            'email_verified_at' => now(),
            'status' => 'PENDING',
        ]);

        // Dispatch Telegram alert only after email is verified
        $companyName = null;
        $industry = null;
        if ($user->role === 'SME') {
            $companyName = $user->smeProfile?->company_name;
            $industry = $user->smeProfile?->industry;
        } elseif ($user->role === 'INVESTOR') {
            $companyName = $user->investorProfile?->organization_name;
            $industry = $user->investorProfile?->industry;
        }

        SendTelegramRegistrationAlertJob::dispatch([
            'full_name' => $user->full_name,
            'role' => $user->role,
            'email' => $user->email,
            'company_name' => $companyName,
            'organization_name' => $companyName,
            'industry' => $industry,
        ]);

        return redirect($frontendUrl . '/email-verified?status=success');
    }
}
