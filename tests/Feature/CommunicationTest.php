<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Message;
use App\Models\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

class CommunicationTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::create([
            'full_name' => 'John Doe',
            'email' => 'john@test.com',
            'password' => bcrypt('password'),
            'role' => 'SME',
            'status' => 'ACTIVE'
        ]);
        $this->token = JWTAuth::fromUser($this->user);
    }

    public function test_user_can_send_and_receive_messages()
    {
        $receiver = User::create([
            'full_name' => 'Investor',
            'email' => 'investor@test.com',
            'password' => bcrypt('password'),
            'role' => 'INVESTOR',
            'status' => 'ACTIVE'
        ]);

        // Send Message
        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/messages', [
                'receiver_id' => $receiver->id,
                'content' => 'Hello there!',
                'chat_id' => 'chat_123'
            ])
            ->assertStatus(201);

        // List Messages
        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/messages')
            ->assertStatus(200)
            ->assertJsonFragment(['content' => 'Hello there!']);
    }

    public function test_user_can_manage_notifications()
    {
        $notification = Notification::create([
            'user_id' => $this->user->id,
            'type' => 'Info',
            'message' => 'Test Notification',
            'is_read' => false
        ]);

        // List Notifications
        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/notifications')
            ->assertStatus(200)
            ->assertJsonFragment(['message' => 'Test Notification']);

        // Mark as Read
        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->patchJson("/api/notifications/{$notification->id}/read")
            ->assertStatus(200);

        $this->assertDatabaseHas('notifications', [
            'id' => $notification->id,
            'is_read' => true
        ]);
    }
}
