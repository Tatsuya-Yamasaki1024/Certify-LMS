<?php

declare(strict_types=1);

namespace Tests\Feature\AiChat;

use App\Models\AiChatConversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiChatAutoTitleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'ai-chat.enabled' => true,
            'ai-chat.gemini.api_key' => 'test-api-key',
            'ai-chat.auto_title.enabled' => true,
        ]);
    }

    /**
     * Gemini API の正常なレスポンスを生成する。
     */
    private function geminiResponse(string $text): array
    {
        return [
            'candidates' => [
                [
                    'content' => [
                        'parts' => [
                            ['text' => $text],
                        ],
                    ],
                ],
            ],
        ];
    }

    // 会話を継続するとGeminiがタイトルを再生成する。
    public function test_title_is_updated_when_conversation_continues(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::sequence()
                ->push($this->geminiResponse('最初の回答'))
                ->push($this->geminiResponse('Laravelの基本'))
                ->push($this->geminiResponse('2回目の回答'))
                ->push($this->geminiResponse('Laravelのルーティングと認証')),
        ]);

        $student = User::factory()->student()->inProgress()->create();

        $conversation = AiChatConversation::query()->create([
            'user_id' => $student->id,
            'title' => '新しい会話',
        ]);

        $this->actingAs($student)
            ->postJson(
                route('ai-chat.conversations.messages.store', $conversation),
                ['content' => 'Laravelについて教えて'],
            )
            ->assertOk()
            ->assertJsonPath('conversation.title', 'Laravelの基本');

        $this->postJson(
            route('ai-chat.conversations.messages.store', $conversation),
            ['content' => 'ルーティングと認証についても教えて'],
        )
            ->assertOk()
            ->assertJsonPath('conversation.title', 'Laravelのルーティングと認証');

        $this->assertSame(
            'Laravelのルーティングと認証',
            $conversation->fresh()->title
        );

        Http::assertSentCount(4);
    }

    // 手動編集したタイトルはGeminiによって上書きされない。
    public function test_manually_edited_title_is_preserved(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response(
                $this->geminiResponse('AIの回答')
            ),
        ]);

        $student = User::factory()->student()->inProgress()->create();

        $conversation = AiChatConversation::query()->create([
            'user_id' => $student->id,
            'title' => '新しい会話',
        ]);

        $this->actingAs($student)
            ->patchJson(
                route('ai-chat.conversations.update', $conversation),
                ['title' => '自分で設定したタイトル'],
            )
            ->assertOk();

        $this->assertTrue($conversation->fresh()->title_manually_edited);

        $this->postJson(
            route('ai-chat.conversations.messages.store', $conversation),
            ['content' => '質問です'],
        )
            ->assertOk()
            ->assertJsonPath('conversation.title', '自分で設定したタイトル');

        Http::assertSentCount(1);
    }

    // 自動タイトル生成がOFFの場合はGeminiにタイトル生成を依頼しない。
    public function test_auto_title_disabled_does_not_generate_title(): void
    {
        config(['ai-chat.auto_title.enabled' => false]);

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response(
                $this->geminiResponse('AIの回答')
            ),
        ]);

        $student = User::factory()->student()->inProgress()->create();

        $conversation = AiChatConversation::query()->create([
            'user_id' => $student->id,
            'title' => '新しい会話',
        ]);

        $this->actingAs($student)
            ->postJson(
                route('ai-chat.conversations.messages.store', $conversation),
                ['content' => '質問です'],
            )
            ->assertOk()
            ->assertJsonPath('conversation.title', '新しい会話');

        Http::assertSentCount(1);
    }

    // タイトル生成に失敗してもAI回答は保存され、最初の質問文が仮タイトルになる。
    public function test_title_generation_failure_does_not_fail_chat(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::sequence()
                ->push($this->geminiResponse('正常な回答'))
                ->push(['error' => ['message' => 'タイトル生成エラー']], 500),
        ]);

        $student = User::factory()->student()->inProgress()->create();

        $conversation = AiChatConversation::query()->create([
            'user_id' => $student->id,
            'title' => '新しい会話',
        ]);

        $this->actingAs($student)
            ->postJson(
                route('ai-chat.conversations.messages.store', $conversation),
                ['content' => '質問です'],
            )
            ->assertOk()
            ->assertJsonPath('assistant_message.content', '正常な回答')
            ->assertJsonPath('conversation.title', '質問です');
        $this->assertDatabaseHas('ai_chat_messages', [
            'conversation_id' => $conversation->id,
            'role' => 'assistant',
            'content' => '正常な回答',
        ]);

        Http::assertSentCount(2);
    }
}
