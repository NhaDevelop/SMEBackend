<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendTelegramRegistrationAlertJob implements ShouldQueue
{
    use Queueable;

    public $userData;

    /**
     * Create a new job instance.
     */
    public function __construct(array $userData)
    {
        $this->userData = $userData;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $token = config('services.telegram.bot_token');
        $chatId = config('services.telegram.chat_id');

        if (!$token || !$chatId) {
            \Log::error('Telegram bot token or chat ID not configured');
            return;
        }

        $message = "🚨 *New User Registration!*\n\n";
        $message .= "*Name:* " . ($this->userData['full_name'] ?? 'N/A') . "\n";
        $message .= "*Role:* " . ($this->userData['role'] ?? 'N/A') . "\n";
        $message .= "*Email:* " . ($this->userData['email'] ?? 'N/A') . "\n";
        
        if (isset($this->userData['company_name'])) {
            $message .= "*Company:* " . $this->userData['company_name'] . "\n";
        } elseif (isset($this->userData['organization_name'])) {
            $message .= "*Organization:* " . $this->userData['organization_name'] . "\n";
        }

        $frontendUrl = env('FRONTEND_URL', 'http://localhost:3000');
        $message .= "\n[Review in Admin Dashboard]({$frontendUrl}/admin/users)";

        \Illuminate\Support\Facades\Http::post("https://api.telegram.org/bot{$token}/sendMessage", [
            'chat_id' => $chatId,
            'text' => $message,
            'parse_mode' => 'Markdown'
        ]);
    }
}
