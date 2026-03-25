<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Goal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

class GoalTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $token;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::create([
            'full_name' => 'Test SME',
            'email' => 'sme@test.com',
            'password' => bcrypt('password'),
            'role' => 'SME',
            'status' => 'ACTIVE'
        ]);

        $this->user->smeProfile()->create(['company_name' => 'Test Co', 'readiness_score' => 0]);
        $this->token = JWTAuth::fromUser($this->user);
    }

    public function test_sme_can_manage_goals()
    {
        // 1. Create Goal
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/sme/goals', [
                'title' => 'Improve Finance',
                'description' => 'Get better reports'
            ]);

        $response->assertStatus(201);
        $goalId = $response->json('id');

        // 2. List Goals
        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/sme/goals')
            ->assertStatus(200)
            ->assertJsonFragment(['title' => 'Improve Finance']);

        // 3. Update Goal
        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->patchJson("/api/sme/goals/{$goalId}", [
                'progress_percentage' => 50
            ])
            ->assertStatus(200)
            ->assertJsonFragment(['progress_percentage' => 50]);

        // 4. Delete Goal
        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->deleteJson("/api/sme/goals/{$goalId}")
            ->assertStatus(200);
    }
}
