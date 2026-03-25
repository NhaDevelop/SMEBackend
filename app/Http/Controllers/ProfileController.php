<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProfileController extends Controller
{
    /**
     * Update basic user information (Full Name, Phone).
     */
    public function updateGeneralProfile(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'full_name' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:255',
        ]);

        $user->update(array_filter($validated));

        return $this->success($user, 'Profile updated successfully');
    }

    /**
     * Update the authenticated SME's profile.
     */
    public function updateSme(Request $request)
    {
        $user = Auth::user();

        if ($user->role !== 'SME') {
            return $this->forbidden('You must be an SME to update this profile.');
        }

        $validated = $request->validate([
            'full_name' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:255',
            'company_name' => 'nullable|string|max:255',
            'registration_number' => 'nullable|string|max:255',
            'industry' => 'nullable|string|max:255',
            'stage' => 'nullable|string|max:255',
            'years_in_business' => 'nullable|string|max:255',
            'team_size' => 'nullable|string|max:255',
            'address' => 'nullable|string',
        ]);

        // Separate user and profile fields
        $userFields = array_intersect_key($validated, array_flip(['full_name', 'phone']));
        $profileFields = array_diff_key($validated, array_flip(['full_name', 'phone']));

        if (!empty($userFields)) {
            $user->update($userFields);
        }

        if (!empty($profileFields)) {
            $user->smeProfile()->update($profileFields);
        }

        return $this->success($user->load('smeProfile'), 'SME profile updated successfully');
    }

    /**
     * Update the authenticated Investor's profile.
     */
    public function updateInvestor(Request $request)
    {
        $user = Auth::user();

        if ($user->role !== 'INVESTOR') {
            return $this->forbidden('You must be an Investor to update this profile.');
        }

        $validated = $request->validate([
            'full_name' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:255',
            'organization_name' => 'nullable|string|max:255',
            'registration_number' => 'nullable|string|max:255',
            'investor_type' => 'nullable|string|max:255',
            'industry' => 'nullable|string|max:255',
            'years_in_business' => 'nullable|string|max:255',
            'team_size' => 'nullable|string|max:255',
            'address' => 'nullable|string',
            'min_ticket_size'        => 'nullable|numeric|min:0',
            'max_ticket_size'        => 'nullable|numeric|min:0',
            'preferred_industries'   => 'nullable|array',
        ]);

        // Separate user and profile fields
        $userFields = array_intersect_key($validated, array_flip(['full_name', 'phone']));
        $profileFields = array_diff_key($validated, array_flip(['full_name', 'phone']));

        if (!empty($userFields)) {
            $user->update($userFields);
        }

        if (!empty($profileFields)) {
            $user->investorProfile()->update($profileFields);
        }

        return $this->success($user->load('investorProfile'), 'Investor profile updated successfully');
    }
}
