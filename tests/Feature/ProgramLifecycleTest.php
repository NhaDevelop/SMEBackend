<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Program;
use App\Models\Template;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

class ProgramLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::create([
            'full_name' => 'SME user',
            'email' => 'sme@test.com',
            'password' => bcrypt('password'),
            'role' => 'SME',
            'status' => 'ACTIVE'
        ]);
        $this->user->smeProfile()->create(['company_name' => 'Test Co', 'readiness_score' => 0]);
        $this->token = auth('api')->login($this->user);
    }

    public function test_sme_can_apply_to_program()
    {
        $template = Template::create(['name' => 'T1', 'industry' => 'Tech']);
        $program = Program::create([
            'name' => 'Startup Cohort',
            'status' => 'Active',
            'template_id' => $template->id
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson("/api/programs/{$program->id}/apply");

        $response->assertStatus(201)
            ->assertJsonFragment(['message' => 'Application submitted successfully']);
    }

    public function test_admin_can_create_program_with_templateId()
    {
        $admin = User::create([
            'full_name' => 'Admin User',
            'email' => 'admin@test.com',
            'password' => bcrypt('password'),
            'role' => 'ADMIN',
            'status' => 'ACTIVE'
        ]);
        $adminToken = auth('api')->login($admin);

        $template = Template::create(['name' => 'Template X', 'industry' => 'Tech']);

        $response = $this->withHeader('Authorization', 'Bearer ' . $adminToken)
            ->postJson('/api/admin/programs', [
            'name' => 'New Program',
            'templateId' => $template->id,
            'status' => 'Published',
            'sector' => 'Technology'
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('templateId', $template->id)
            ->assertJsonPath('template', 'Template X');

        $this->assertDatabaseHas('programs', [
            'name' => 'New Program',
            'template_id' => $template->id,
            'created_by_user_id' => $admin->id
        ]);
    }

    public function test_admin_can_update_program()
    {
        $admin = User::create([
            'full_name' => 'Admin User',
            'email' => 'admin-update@test.com',
            'password' => bcrypt('password'),
            'role' => 'ADMIN',
            'status' => 'ACTIVE'
        ]);
        $adminToken = auth('api')->login($admin);

        $program = Program::create([
            'name' => 'Old Program',
            'status' => 'Draft'
        ]);

        $template = Template::create(['name' => 'New Template', 'industry' => 'Tech']);

        $response = $this->withHeader('Authorization', 'Bearer ' . $adminToken)
            ->putJson("/api/admin/programs/{$program->id}", [
                'name' => 'Updated Program Name',
                'templateId' => $template->id,
                'status' => 'Published',
                'startDate' => '2026-04-01',
                'investmentAmount' => '50000'
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('name', 'Updated Program Name')
            ->assertJsonPath('templateId', $template->id)
            ->assertJsonPath('template', 'New Template')
            ->assertJsonPath('startDate', '2026-04-01')
            ->assertJsonPath('investmentAmount', '50000');

        $this->assertDatabaseHas('programs', [
            'id' => $program->id,
            'name' => 'Updated Program Name',
            'template_id' => $template->id,
            'status' => 'Published',
            'start_date' => '2026-04-01 00:00:00',
            'investment_amount' => '50000'
        ]);
    }

    public function test_admin_can_update_all_program_fields()
    {
        $admin = User::create([
            'full_name' => 'Admin User',
            'email' => 'admin-full@test.com',
            'password' => bcrypt('password'),
            'role' => 'ADMIN',
            'status' => 'ACTIVE'
        ]);
        $adminToken = auth('api')->login($admin);

        $template = \App\Models\Template::create(['name' => 'New Template', 'status' => 'Active']);
        $program = Program::create(['name' => 'Initial Name']);

        $response = $this->withHeader('Authorization', 'Bearer ' . $adminToken)
            ->putJson("/api/admin/programs/{$program->id}", [
                'name' => 'Full Update',
                'description' => 'New Description',
                'templateId' => $template->id,
                'status' => 'Published',
                'startDate' => '2026-05-01',
                'endDate' => '2026-11-01',
                'sector' => 'Technology',
                'investmentAmount' => '75000',
                'benefits' => ['Benefit A', 'Benefit B']
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('programs', [
            'id' => $program->id,
            'name' => 'Full Update',
            'description' => 'New Description',
            'template_id' => $template->id,
            'status' => 'Published',
            'start_date' => '2026-05-01 00:00:00',
            'end_date' => '2026-11-01 00:00:00',
            'sector' => 'Technology',
            'investment_amount' => '75000'
        ]);

        // Verify JSON benefits
        $updated = Program::find($program->id);
        $this->assertEquals(['Benefit A', 'Benefit B'], $updated->benefits);
    }

    public function test_admin_can_delete_program()
    {
        $admin = User::create([
            'full_name' => 'Admin User',
            'email' => 'admin-delete@test.com',
            'password' => bcrypt('password'),
            'role' => 'ADMIN',
            'status' => 'ACTIVE'
        ]);
        $adminToken = auth('api')->login($admin);

        $program = Program::create(['name' => 'To Be Deleted']);

        $response = $this->withHeader('Authorization', 'Bearer ' . $adminToken)
            ->deleteJson("/api/admin/programs/{$program->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('programs', ['id' => $program->id]);
    }

    public function test_admin_can_view_program_details()
    {
        $admin = User::create([
            'full_name' => 'Admin User',
            'email' => 'admin-view@test.com',
            'password' => bcrypt('password'),
            'role' => 'ADMIN',
            'status' => 'ACTIVE'
        ]);
        $adminToken = auth('api')->login($admin);

        $program = Program::create([
            'name' => 'Detail Program',
            'sector' => 'Finance'
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $adminToken)
            ->getJson("/api/admin/programs/{$program->id}");

        $response->assertStatus(200)
            ->assertJsonPath('name', 'Detail Program')
            ->assertJsonPath('sector', 'Finance');
    }
}