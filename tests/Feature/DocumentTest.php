<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Document;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

class DocumentTest extends TestCase
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
        $this->token = JWTAuth::fromUser($this->user);
        Storage::fake('public');
    }

    public function test_user_can_upload_and_list_documents()
    {
        $file = UploadedFile::fake()->create('contract.pdf', 500);

        // Upload
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/documents', [
                'file' => $file,
                'category' => 'Legal',
                'description' => 'Test document'
            ]);

        $response->assertStatus(201);
        
        // List
        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/documents')
            ->assertStatus(200)
            ->assertJsonFragment(['name' => 'contract.pdf']);
    }
}
