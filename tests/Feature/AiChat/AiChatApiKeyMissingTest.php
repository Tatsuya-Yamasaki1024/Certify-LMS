<?php

declare(strict_types=1);

namespace Tests\Feature\AiChat;

use App\Enums\AiChatMessageRole;
use App\Enums\AiChatMessageStatus;
use App\Models\AiChatConversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiChatApiKeyMissingTest extends TestCase
{
    use RefreshDatabase;

    // APIキー未設定時は503と利用できない旨の案内を返す
    public function test_missing_api_key_returns_503(): void
    {
        config([
            'ai-chat.enabled' => true,
            'ai-chat.gemini.api_key' => null,
        ]);

        Http::fake();

        $student = User::factory()->student()->inProgress()->create();

        $conversation = AiChatConversation::query()->create([
            'user_id' => $student->id,
            'title' => 'APIキー未設定テスト',
        ]);

        $this->actingAs($student)
            ->postJson(
                route('ai-chat.conversations.messages.store', $conversation),
                ['content' => '質問です'],
            )
            ->assertStatus(503)
            ->assertJsonPath(
                'message',
                'AIチャットは現在利用できません。管理者にお問い合わせください。',
            );

        Http::assertNothingSent();

        $this->assertDatabaseHas('ai_chat_messages', [
            'conversation_id' => $conversation->id,
            'role' => AiChatMessageRole::User->value,
            'status' => AiChatMessageStatus::Error->value,
            'content' => '質問です',
        ]);
    }
}
