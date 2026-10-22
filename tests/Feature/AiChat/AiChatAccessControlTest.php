<?php

declare(strict_types=1);

namespace Tests\Feature\AiChat;

use App\Enums\UserStatus;
use App\Models\AiChatConversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiChatAccessControlTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'ai-chat.enabled' => true,
            'ai-chat.gemini.api_key' => 'test-api-key',
        ]);
    }

    // 学習中の受講生はAIチャット画面を閲覧できる。
    public function test_in_progress_student_can_access_ai_chat(): void
    {
        $student = User::factory()->student()->inProgress()->create();

        $this->actingAs($student)
            ->get(route('ai-chat.index'))
            ->assertOk();
    }

    // 卒業済み・退会済み・招待中の受講生はAIチャットを利用できない。
    public function test_inactive_students_cannot_access_ai_chat(): void
    {
        foreach ([
            UserStatus::Graduated,
            UserStatus::Withdrawn,
            UserStatus::Invited,
        ] as $status) {
            $student = User::factory()->student()->create([
                'status' => $status->value,
            ]);

            $this->actingAs($student)
                ->get(route('ai-chat.index'))
                ->assertForbidden();
        }
    }

    // コーチと管理者はAIチャットを利用できない。
    public function test_coach_and_admin_cannot_access_ai_chat(): void
    {
        $coach = User::factory()->coach()->inProgress()->create();
        $admin = User::factory()->admin()->inProgress()->create();

        $this->actingAs($coach)
            ->get(route('ai-chat.index'))
            ->assertForbidden();

        $this->actingAs($admin)
            ->get(route('ai-chat.index'))
            ->assertForbidden();
    }

    // 他の受講生の会話は閲覧・タイトル変更・削除・メッセージ送信できない。
    public function test_student_cannot_operate_another_students_conversation(): void
    {
        $owner = User::factory()->student()->inProgress()->create();
        $otherStudent = User::factory()->student()->inProgress()->create();

        $conversation = AiChatConversation::query()->create([
            'user_id' => $owner->id,
            'title' => '所有者の会話',
        ]);

        $this->actingAs($otherStudent)
            ->get(route('ai-chat.conversations.show', $conversation))
            ->assertNotFound();

        $this->actingAs($otherStudent)
            ->patch(route('ai-chat.conversations.update', $conversation), [
                'title' => '不正な変更',
            ])
            ->assertNotFound();

        $this->actingAs($otherStudent)
            ->delete(route('ai-chat.conversations.destroy', $conversation))
            ->assertNotFound();

        $this->actingAs($otherStudent)
            ->postJson(route('ai-chat.conversations.messages.store', $conversation), [
                'content' => '不正な質問',
            ])
            ->assertNotFound();

        $this->assertDatabaseHas('ai_chat_conversations', [
            'id' => $conversation->id,
            'user_id' => $owner->id,
            'title' => '所有者の会話',
        ]);

        $this->assertDatabaseMissing('ai_chat_messages', [
            'conversation_id' => $conversation->id,
            'content' => '不正な質問',
        ]);
    }
}
