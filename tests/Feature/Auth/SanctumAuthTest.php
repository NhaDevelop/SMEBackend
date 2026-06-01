<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SanctumAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        auth()->forgetGuards();
        auth()->shouldUse('web');
        parent::tearDown();
    }

    public function test_login_returns_sanctum_token(): void
    {
        $user = User::factory()->create([
            'role' => 'SME',
            'status' => 'ACTIVE',
            'password' => bcrypt('password'),
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => ['access_token', 'token_type', 'expires_in'],
            ]);

        $token = $response->json('data.access_token');
        $this->assertNotEmpty($token);
        $this->assertStringContainsString('|', $token);
    }

    public function test_pending_user_cannot_login(): void
    {
        $user = User::factory()->create([
            'status' => 'PENDING',
            'password' => bcrypt('password'),
        ]);

        $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertForbidden();
    }

    public function test_authenticated_user_can_access_profile(): void
    {
        $user = User::factory()->create(['role' => 'ADMIN', 'status' => 'ACTIVE']);
        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/auth/profile')
            ->assertOk()
            ->assertJsonPath('data.email', $user->email);
    }

    public function test_logout_revokes_current_token(): void
    {
        $user = User::factory()->create(['role' => 'SME', 'status' => 'ACTIVE']);
        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/auth/logout')
            ->assertOk();

        $this->assertDatabaseCount('personal_access_tokens', 0);

        // PHPUnit reuses the app container; clear cached guard state (not an issue per real HTTP request).
        auth()->forgetGuards();

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/auth/profile')
            ->assertUnauthorized();
    }

    public function test_refresh_issues_new_token(): void
    {
        $user = User::factory()->create(['role' => 'SME', 'status' => 'ACTIVE']);
        $oldToken = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $oldToken)
            ->postJson('/api/auth/refresh');

        $response->assertOk();
        $newToken = $response->json('data.access_token');
        $this->assertNotEquals($oldToken, $newToken);

        auth()->forgetGuards();

        $this->withHeader('Authorization', 'Bearer ' . $oldToken)
            ->getJson('/api/auth/profile')
            ->assertUnauthorized();

        $this->withHeader('Authorization', 'Bearer ' . $newToken)
            ->getJson('/api/auth/profile')
            ->assertOk();
    }
}
