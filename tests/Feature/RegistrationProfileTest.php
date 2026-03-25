<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;

class RegistrationProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_sme_registration_saves_profile_data()
    {
        $response = $this->postJson('/api/auth/register', [
            'full_name' => 'John SME',
            'email' => 'john@sme.com',
            'password' => 'password123',
            'role' => 'SME',
            'companyName' => 'Frontend Tech',
            'industry' => 'Software',
            'teamSize' => '11-50'
        ]);

        $response->assertStatus(201);

        $user = User::where('email', 'john@sme.com')->first();
        $this->assertNotNull($user->smeProfile);
        $this->assertEquals('Frontend Tech', $user->smeProfile->company_name);
        $this->assertEquals('Software', $user->smeProfile->industry);
        $this->assertEquals('11-50', $user->smeProfile->team_size);
    }

    public function test_investor_registration_saves_profile_data()
    {
        $response = $this->postJson('/api/auth/register', [
            'full_name' => 'Jane Investor',
            'email' => 'jane@investor.com',
            'password' => 'password123',
            'role' => 'INVESTOR',
            'organizationName' => 'Jane Capital',
            'industry' => 'Finance',
            'minTicketSize' => 50000
        ]);

        $response->assertStatus(201);

        $user = User::where('email', 'jane@investor.com')->first();
        $this->assertNotNull($user->investorProfile);
        $this->assertEquals('Jane Capital', $user->investorProfile->organization_name);
        $this->assertEquals('Finance', $user->investorProfile->industry);
        $this->assertEquals(50000, $user->investorProfile->min_ticket_size);
    }
}
