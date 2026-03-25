<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\SmeProfile;
use App\Models\Assessment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

class InvestorTest extends TestCase
{
    use RefreshDatabase;

    protected $investor;
    protected $token;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->investor = User::create([
            'full_name' => 'Test Investor',
            'email' => 'investor@test.com',
            'password' => bcrypt('password'),
            'role' => 'INVESTOR',
            'status' => 'ACTIVE'
        ]);

        $this->token = JWTAuth::fromUser($this->investor);
    }

    public function test_investor_can_see_dealflow()
    {
        $sme = User::create([
            'full_name' => 'SME Co',
            'email' => 'sme@test.com',
            'password' => bcrypt('password'),
            'role' => 'SME',
            'status' => 'ACTIVE'
        ]);

        $sme->smeProfile()->create([
            'company_name' => 'SME Co',
            'readiness_score' => 75,
            'risk_level' => 'Medium'
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/investor/dealflow');

        $response->assertStatus(200)
            ->assertJsonFragment(['name' => 'SME Co', 'readiness_score' => '75.00']);
    }

    public function test_investor_can_see_analytics()
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/investor/analytics');

        $response->assertStatus(200)
            ->assertJsonStructure(['total_portfolio', 'average_readiness', 'risk_metrics']);
    }
}
