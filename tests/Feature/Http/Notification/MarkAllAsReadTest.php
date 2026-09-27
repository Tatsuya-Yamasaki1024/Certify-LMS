<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Notification;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class MarkAllAsReadTest extends TestCase
{
    use RefreshDatabase;

    // 自分の未読通知をすべて既読にできることを確認する。
    public function test_student_can_mark_all_own_notifications_as_read(): void
    {
        // Arrange
        $student = User::factory()->student()->inProgress()->create();

        $notification1 = $student->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => 'App\Notifications\Chat\ChatMessageReceivedNotification',
            'data' => [
                'notification_type' => 'chat_message_received',
                'title' => '通知1',
                'message' => '未読通知1',
            ],
        ]);

        $notification2 = $student->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => 'App\Notifications\Qa\QaReplyReceivedNotification',
            'data' => [
                'notification_type' => 'qa_reply_received',
                'title' => '通知2',
                'message' => '未読通知2',
            ],
        ]);

        // Act
        $response = $this->actingAs($student)
            ->post(route('notifications.markAllAsRead'));

        // Assert
        $response->assertRedirect(route('notifications.index'));
        $response->assertSessionHas(
            'success',
            '通知をすべて既読にしました。',
        );

        $this->assertNotNull($notification1->fresh()->read_at);
        $this->assertNotNull($notification2->fresh()->read_at);
    }

    // 既読通知が既読のまま維持されることを確認する。
    public function test_read_notifications_remain_read(): void
    {
        // Arrange
        $student = User::factory()->student()->inProgress()->create();

        $notification = $student->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => 'App\Notifications\Chat\ChatMessageReceivedNotification',
            'data' => [
                'notification_type' => 'chat_message_received',
                'title' => '既読通知',
                'message' => 'すでに既読の通知です。',
            ],
        ]);

        $notification->markAsRead();
        $readAt = $notification->fresh()->read_at;

        // Act
        $response = $this->actingAs($student)
            ->post(route('notifications.markAllAsRead'));

        // Assert
        $response->assertRedirect(route('notifications.index'));

        $this->assertEquals(
            $readAt,
            $notification->fresh()->read_at,
        );
    }

    // 他のユーザーの未読通知が既読にならないことを確認する。
    public function test_other_users_notifications_remain_unread(): void
    {
        // Arrange
        $student = User::factory()->student()->inProgress()->create();
        $otherStudent = User::factory()->student()->inProgress()->create();

        $notification = $otherStudent->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => 'App\Notifications\Chat\ChatMessageReceivedNotification',
            'data' => [
                'notification_type' => 'chat_message_received',
                'title' => '他ユーザーの通知',
                'message' => '他ユーザーの未読通知です。',
            ],
        ]);

        // Act
        $this->actingAs($student)
            ->post(route('notifications.markAllAsRead'));

        // Assert
        $this->assertNull($notification->fresh()->read_at);
    }
}
