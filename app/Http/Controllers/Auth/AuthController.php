<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use App\Jobs\SendTelegramRegistrationAlertJob;
use App\Mail\EmailVerificationMail;
use Laravel\Sanctum\PersonalAccessToken;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $mappings = [
            'companyName' => 'company_name',
            'registrationNumber' => 'registration_number',
            'registrationNo' => 'registration_number',
            'registration_no' => 'registration_number',
            'yearsInBusiness' => 'years_in_business',
            'teamSize' => 'team_size',
            'employees' => 'team_size',
            'websiteUrl' => 'website_url',
            'minTicketSize' => 'min_ticket_size',
            'maxTicketSize' => 'max_ticket_size',
            'organizationName' => 'organization_name',
        ];

        foreach ($mappings as $frontend => $backend) {
            if ($request->has($frontend) && !$request->has($backend)) {
                $request->merge([$backend => $request->$frontend]);
            }
        }

        $existingRejected = User::where('email', $request->input('email'))
            ->whereIn('status', ['REJECTED', 'PENDING_VERIFICATION'])
            ->first();

        if ($existingRejected) {
            $existingRejected->smeProfile()->delete();
            $existingRejected->investorProfile()->delete();
            $existingRejected->delete();
        }

        $validated = $request->validate([
            'full_name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'phone' => 'nullable|string|max:20',
            'password' => 'required|string|min:8',
            'role' => 'required|in:SME,INVESTOR',
            'company_name' => 'nullable|string|max:255',
            'registration_number' => 'nullable|string|max:255',
            'industry' => 'nullable|string|max:255',
            'stage' => 'nullable|string',
            'years_in_business' => 'nullable|string',
            'team_size' => 'nullable|string',
            'address' => 'nullable|string',
            'website_url' => 'nullable|string',
            'investor_type' => 'nullable|string',
            'min_ticket_size' => 'nullable|numeric',
            'max_ticket_size' => 'nullable|numeric',
            'organization_name' => 'nullable|string',
            'registration_document' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
        ]);

        $user = User::create([
            'full_name' => $validated['full_name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'password' => Hash::make($validated['password']),
            'role' => $validated['role'],
            'status' => 'PENDING_VERIFICATION',
        ]);

        $docPath = null;
        if ($request->hasFile('registration_document')) {
            $docPath = $request->file('registration_document')->store('registration_documents');
        }

        if ($user->role === 'SME') {
            $user->smeProfile()->create([
                'company_name' => $validated['company_name'] ?? null,
                'registration_number' => $validated['registration_number'] ?? null,
                'industry' => $validated['industry'] ?? null,
                'stage' => $validated['stage'] ?? null,
                'years_in_business' => $validated['years_in_business'] ?? null,
                'team_size' => $validated['team_size'] ?? null,
                'address' => $validated['address'] ?? null,
                'website_url' => $validated['website_url'] ?? null,
                'registration_document' => $docPath,
            ]);
        } else if ($validated['role'] === 'INVESTOR') {
            $user->investorProfile()->create([
                'organization_name' => $validated['organization_name'] ?? $validated['company_name'] ?? null,
                'investor_type' => $validated['investor_type'] ?? null,
                'min_ticket_size' => $validated['min_ticket_size'] ?? null,
                'max_ticket_size' => $validated['max_ticket_size'] ?? null,
                'industry' => $validated['industry'] ?? null,
                'address' => $validated['address'] ?? null,
                'registration_number' => $validated['registration_number'] ?? null,
                'years_in_business' => $validated['years_in_business'] ?? null,
                'team_size' => $validated['team_size'] ?? null,
                'website_url' => $validated['website_url'] ?? null,
                'registration_document' => $docPath,
            ]);
        }

        try {
            $verificationUrl = URL::temporarySignedRoute(
                'email.verify',
                now()->addHours(24),
                ['id' => $user->id, 'hash' => sha1($user->email)]
            );
            Mail::to($user->email)->send(new EmailVerificationMail($user, $verificationUrl));
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to send verification email: ' . $e->getMessage());
        }



        return $this->success(null, 'Registration successful, awaiting admin approval', 201);
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required'
        ]);

        $user = User::where('email', $credentials['email'])->first();

        if (!$user || !Hash::check($credentials['password'], $user->password)) {
            return $this->unauthorized('Invalid email or password');
        }

        if ($user->status !== 'ACTIVE') {
            return $this->forbidden('Account is pending approval or inactive');
        }

        $user->update(['last_login_at' => now()]);

        // One active API token per login (revoke previous sessions)
        $user->tokens()->delete();

        return $this->respondWithToken($user->createToken('api-client'));
    }

    public function profile(Request $request)
    {
        $user = $request->user();

        if ($user->role === 'SME') {
            $user->load('smeProfile');
        } elseif ($user->role === 'INVESTOR') {
            $user->load('investorProfile');
        }

        return $this->success($user);
    }

    public function logout(Request $request)
    {
        $this->revokeCurrentAccessToken($request);

        return $this->success(null, 'Successfully logged out');
    }

    public function refresh(Request $request)
    {
        $user = $request->user();
        $this->revokeCurrentAccessToken($request);

        return $this->respondWithToken($user->createToken('api-client'), 'Token refreshed');
    }

    /**
     * Revoke the bearer token used on this request (Sanctum personal access token).
     */
    protected function revokeCurrentAccessToken(Request $request): void
    {
        $user = $request->user();
        if (!$user) {
            return;
        }

        $token = $user->currentAccessToken();
        if ($token) {
            $token->delete();
            return;
        }

        if ($bearer = $request->bearerToken()) {
            PersonalAccessToken::findToken($bearer)?->delete();
        }
    }

    protected function respondWithToken($accessToken, string $message = 'Login successful')
    {
        $expiresMinutes = config('sanctum.expiration');

        return $this->success([
            'access_token' => $accessToken->plainTextToken,
            'token_type' => 'Bearer',
            'expires_in' => $expiresMinutes ? $expiresMinutes * 60 : null,
        ], $message);
    }
}
