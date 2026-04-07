<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        // Handle camelCase and alternative names from frontend
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

        $validated = $request->validate([
            'full_name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'phone' => 'nullable|string|max:20',
            'password' => 'required|string|min:8',
            'role' => 'required|in:SME,INVESTOR',
            // Profile fields
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
            'status' => 'PENDING',
        ]);

        $docPath = null;
        if ($request->hasFile('registration_document')) {
            $docPath = $request->file('registration_document')->store('registration_documents', 'public');
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
        }
        else if ($validated['role'] === 'INVESTOR') {
            $user->investorProfile()->create([
                'organization_name' => $validated['organization_name'] ?? $validated['company_name'] ?? null,
                'investor_type' => $validated['investor_type'] ?? null,
                'min_ticket_size' => $validated['min_ticket_size'] ?? null,
                'max_ticket_size' => $validated['max_ticket_size'] ?? null,
                'industry' => $validated['industry'] ?? null,
                'address' => $validated['address'] ?? null,
                'registration_document' => $docPath,
            ]);
        }

        return $this->success(null, 'Registration successful, awaiting admin approval', 201);
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required'
        ]);

        \Log::info('Login attempt from frontend:', $credentials);

        if (!$token = auth('api')->attempt($credentials)) {
            \Log::warning('Login failed for email: ' . $credentials['email']);
            return $this->unauthorized('Invalid email or password');
        }

        // Before returning the token, make sure the user is ACTIVE
        $user = auth('api')->user();
        if ($user->status !== 'ACTIVE') {
            auth('api')->logout();
            return $this->forbidden('Account is pending approval or inactive');
        }

        // Update last login
        $user->update(['last_login_at' => now()]);

        return $this->respondWithToken($token);
    }

    public function profile()
    {
        // Eager load the profile based on the user's role
        $user = auth('api')->user();

        if ($user->role === 'SME') {
            $user->load('smeProfile');
        }
        elseif ($user->role === 'INVESTOR') {
            $user->load('investorProfile');
        }

        return $this->success($user);
    }

    public function logout()
    {
        auth('api')->logout();
        return $this->success(null, 'Successfully logged out');
    }

    public function refresh()
    {
        return $this->respondWithToken(auth('api')->refresh());
    }

    protected function respondWithToken($token)
    {
        return $this->success([
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => auth('api')->factory()->getTTL() * 60
        ], 'Login successful');
    }
}