<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Notification;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class MarkAsReadNotificationTypesTest extends TestCase
{
    use RefreshDatabase;

    // チャット通知を既読にした場合、チャット画面へ遷移することを確認する。
    public function test_chat_message_notification_redirects_to_chat(): void
    {
        // Arrange
        $student = User::factory()
            ->student()
            ->inProgress()
            ->create();

        $chatRoomId = (string) Str::ulid();

        $notification = $student->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => 'App\Notifications\Chat\ChatMessageReceivedNotification',
            'data' => [
                'notification_type' => 'chat_message_received',
                'title' => '新しいメッセージがあります',
                'message' => '新しいメッセージです。',
                'chat_room_id' => $chatRoomId,
            ],
        ]);

        // Act
        $response = $this->actingAs($student)
            ->post(route('notifications.markAsRead', $notification));

        // Assert
        $response->assertRedirect(
            route('chat.show', $chatRoomId),
        );

        $this->assertNotNull(
            $notification->fresh()->read_at,
        );
    }

    // Q&A回答通知を既読にした場合、質問詳細画面へ遷移することを確認する。
    public function test_qa_reply_notification_redirects_to_qa_thread(): void
    {
        // Arrange
        $student = User::factory()
            ->student()
            ->inProgress()
            ->create();

        $qaThreadId = (string) Str::ulid();

        $notification = $student->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => 'App\Notifications\Qa\QaReplyReceivedNotification',
            'data' => [
                'notification_type' => 'qa_reply_received',
                'title' => '質問に回答が投稿されました',
                'message' => '回答本文です。',
                'qa_thread_id' => $qaThreadId,
            ],
        ]);

        // Act
        $response = $this->actingAs($student)
            ->post(route('notifications.markAsRead', $notification));

        // Assert
        $response->assertRedirect(
            route('qa-board.show', $qaThreadId),
        );

        $this->assertNotNull(
            $notification->fresh()->read_at,
        );
    }

    // 面談予約通知を既読にした場合、面談詳細画面へ遷移することを確認する。
    public function test_meeting_reserved_notification_redirects_to_meeting(): void
    {
        // Arrange
        $student = User::factory()
            ->student()
            ->inProgress()
            ->create();

        $meetingId = (string) Str::ulid();

        $notification = $student->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => 'App\Notifications\Meeting\MeetingReservedNotification',
            'data' => [
                'notification_type' => 'meeting_reserved',
                'title' => '面談が予約されました',
                'message' => '受講生から面談の予約が入りました。',
                'meeting_id' => $meetingId,
            ],
        ]);

        // Act
        $response = $this->actingAs($student)
            ->post(route('notifications.markAsRead', $notification));

        // Assert
        $response->assertRedirect(
            route('meetings.show', $meetingId),
        );

        $this->assertNotNull(
            $notification->fresh()->read_at,
        );
    }

    // 面談キャンセル通知を既読にした場合、面談詳細画面へ遷移することを確認する。
    public function test_meeting_canceled_notification_redirects_to_meeting(): void
    {
        // Arrange
        $student = User::factory()
            ->student()
            ->inProgress()
            ->create();

        $meetingId = (string) Str::ulid();

        $notification = $student->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => 'App\Notifications\Meeting\MeetingCanceledNotification',
            'data' => [
                'notification_type' => 'meeting_canceled',
                'title' => '面談がキャンセルされました',
                'message' => '面談予約がキャンセルされました。',
                'meeting_id' => $meetingId,
            ],
        ]);

        // Act
        $response = $this->actingAs($student)
            ->post(route('notifications.markAsRead', $notification));

        // Assert
        $response->assertRedirect(
            route('meetings.show', $meetingId),
        );

        $this->assertNotNull(
            $notification->fresh()->read_at,
        );
    }

    // 他のユーザーの通知を既読にできないことを確認する。
    public function test_student_cannot_mark_another_users_notification_as_read(): void
    {
        // Arrange
        $student = User::factory()
            ->student()
            ->inProgress()
            ->create();

        $otherStudent = User::factory()
            ->student()
            ->inProgress()
            ->create();

        $notification = $otherStudent->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => 'App\Notifications\Chat\ChatMessageReceivedNotification',
            'data' => [
                'notification_type' => 'chat_message_received',
                'title' => '他ユーザーの通知',
                'message' => '他ユーザーの通知本文です。',
                'chat_room_id' => (string) Str::ulid(),
            ],
        ]);

        // Act
        $response = $this->actingAs($student)
            ->post(route('notifications.markAsRead', $notification));

        // Assert
        $response->assertNotFound();

        $this->assertNull(
            $notification->fresh()->read_at,
        );
    }
}
