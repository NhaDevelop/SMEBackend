<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Models\Template;
use App\Models\Program;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

class TemplateStatusTest extends TestCase
{
    use RefreshDatabase;

    protected $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'ADMIN', 'status' => 'ACTIVE']);
    }

    public function test_admin_can_update_template_status()
    {
        $template = Template::create(['name' => 'Draft Template', 'status' => 'Draft']);
        $token = auth('api')->login($this->admin);

        $response = $this->patchJson("/api/admin/templates/{$template->id}/status", [
            'status' => 'Active'
        ], ['Authorization' => "Bearer $token"]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'Active');
        
        $this->assertEquals('Active', $template->refresh()->status);
    }

    public function test_can_fetch_only_active_templates()
    {
        Template::create(['name' => 'Draft', 'status' => 'Draft']);
        Template::create(['name' => 'Active', 'status' => 'Active']);
        Template::create(['name' => 'Archived', 'status' => 'Archived']);
        
        $token = auth('api')->login($this->admin);

        $response = $this->getJson("/api/admin/templates/active", [
            'Authorization' => "Bearer $token"
        ]);

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_cannot_create_program_with_inactive_template()
    {
        $template = Template::create(['name' => 'Draft Template', 'status' => 'Draft']);
        $token = auth('api')->login($this->admin);

        $response = $this->postJson("/api/admin/programs", [
            'name' => 'New Program',
            'template_id' => $template->id,
            'status' => 'Coming Soon'
        ], ['Authorization' => "Bearer $token"]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['template_id']);
    }

    public function test_can_create_program_with_active_template()
    {
        $template = Template::create(['name' => 'Active Template', 'status' => 'Active']);
        $token = auth('api')->login($this->admin);

        $response = $this->postJson("/api/admin/programs", [
            'name' => 'New Program',
            'template_id' => $template->id,
            'status' => 'Coming Soon'
        ], ['Authorization' => "Bearer $token"]);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'New Program');
    }
}
