<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Template;
use App\Models\Question;
use App\Models\Assessment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

class AssessmentTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $token;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Seed some basic data
        $this->user = User::create([
            'full_name' => 'Test SME',
            'email' => 'test@sme.com',
            'password' => bcrypt('password'),
            'role' => 'SME',
            'status' => 'ACTIVE'
        ]);

        $this->user->smeProfile()->create(['company_name' => 'Test Co', 'readiness_score' => 0]);
        $this->token = JWTAuth::fromUser($this->user);
    }

    public function test_sme_can_get_questions()
    {
        $template = Template::create([
            'name' => 'Test Template',
            'status' => 'Active',
            'industry' => 'General'
        ]);

        \App\Models\Pillar::create([
            'id' => 1,
            'name' => 'Team',
            'weight' => 100
        ]);

        Question::create([
            'template_id' => $template->id,
            'pillar_id' => 1,
            'text' => 'Sample Question?',
            'type' => 'Yes/No',
            'weight' => 10
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/assessment/questions');

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => ['template', 'questions']]);
    }

    public function test_sme_can_start_assessment()
    {
        $template = Template::create([
            'name' => 'Test Template',
            'status' => 'Active',
            'industry' => 'General'
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/assessment/start', [
                'template_id' => $template->id
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['data' => ['assessment_id'], 'message']);
    }

    public function test_sme_can_submit_assessment()
    {
        $template = Template::create([
            'name' => 'Test Template',
            'status' => 'Active',
            'industry' => 'General'
        ]);

        \App\Models\Pillar::create([
            'id' => 1,
            'name' => 'Team',
            'weight' => 100
        ]);

        $q = Question::create([
            'template_id' => $template->id,
            'pillar_id' => 1,
            'text' => 'Sample Question?',
            'type' => 'Yes/No',
            'weight' => 10
        ]);

        $assessment = Assessment::create([
            'sme_id' => $this->user->smeProfile->id,
            'template_id' => $template->id,
            'status' => 'In Progress',
            'started_at' => now()
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson("/api/assessment/{$assessment->id}/submit", [
                'answers' => [
                    [
                        'question_id' => $q->id,
                        'value' => true
                    ]
                ]
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.total_score', 100);
    }

    public function test_sme_can_view_assessment_history()
    {
        $template = Template::create(['name' => 'T1', 'industry' => 'Tech']);
        Assessment::create([
            'sme_id' => $this->user->smeProfile->id,
            'template_id' => $template->id,
            'status' => 'Completed',
            'total_score' => 85,
            'started_at' => now(),
            'completed_at' => now()
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/assessment/history');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_3_tier_weighting_system()
    {
        $template = Template::create(['name' => 'Weighted Template', 'status' => 'Active', 'industry' => 'Tech']);
        
        // Level 1: Pillar (50% of total)
        \App\Models\Pillar::create(['id' => 2, 'name' => 'Tech', 'weight' => 50]);
        // Level 1: Pillar 2 (50% of total)
        \App\Models\Pillar::create(['id' => 3, 'name' => 'Finance', 'weight' => 50]);

        // Level 2: Question (Weight 100 inside the Tech pillar)
        $q = Question::create([
            'template_id' => $template->id,
            'pillar_id' => 2,
            'text' => 'CEO Experience?',
            'type' => 'Multiple Choice',
            'weight' => 100,
            'options' => [
                ['label' => 'Yes, Full Time', 'points' => 100],
                ['label' => 'Yes, Part Time', 'points' => 50],
                ['label' => 'No', 'points' => 0],
            ]
        ]);

        $assessment = Assessment::create([
            'sme_id' => $this->user->smeProfile->id,
            'template_id' => $template->id,
            'status' => 'In Progress',
            'started_at' => now()
        ]);

        // Scenario: SME selects "Yes, Part Time" (50% of Level 2 weight)
        // Level 3 Calculation: (50/100) * 100 = 50 points earned.
        // Pillar Level: (50 earned / 100 max) * 100 = 50% pillar score.
        // Platform Level: (50% pillar score * 50 pillar weight) / 100 = 25 total score contribution.

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson("/api/assessment/{$assessment->id}/submit", [
                'answers' => [
                    [
                        'question_id' => $q->id,
                        'value' => 'Yes, Part Time'
                    ]
                ]
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.total_score', 25);
    }
}
