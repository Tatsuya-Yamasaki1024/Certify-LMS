<?php

declare(strict_types=1);

namespace Tests\Feature\AiChat;

use App\Models\AiChatConversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiChatFeatureDisabledTest extends TestCase
{
    use RefreshDatabase;

    // AIチャット機能OFF時に会話一覧へアクセスできない
    public function test_ai_chat_index_is_unavailable_when_disabled(): void
    {
        config(['ai-chat.enabled' => false]);

        $student = User::factory()->student()->inProgress()->create();

        $this->actingAs($student)
            ->get(route('ai-chat.index'))
            ->assertStatus(503);
    }

    // AIチャット機能OFF時に会話の作成・表示・編集・削除・送信ができない
    public function test_ai_chat_operations_are_unavailable_when_disabled(): void
    {
        config(['ai-chat.enabled' => false]);

        $student = User::factory()->student()->inProgress()->create();

        $conversation = AiChatConversation::query()->create([
            'user_id' => $student->id,
            'title' => 'テスト会話',
        ]);

        $this->actingAs($student);

        $this->post(route('ai-chat.conversations.store'))
            ->assertStatus(503);

        $this->get(route('ai-chat.conversations.show', $conversation))
            ->assertStatus(503);

        $this->get(route('ai-chat.conversations.section', ['section_id' => 'test']))
            ->assertStatus(503);

        $this->patch(route('ai-chat.conversations.update', $conversation), [
            'title' => '変更後のタイトル',
        ])->assertStatus(503);

        $this->delete(route('ai-chat.conversations.destroy', $conversation))
            ->assertStatus(503);

        $this->post(route('ai-chat.conversations.messages.store', $conversation), [
            'content' => 'テストメッセージ',
        ])->assertStatus(503);
    }
}
