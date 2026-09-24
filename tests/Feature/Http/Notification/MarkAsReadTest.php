<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Notification;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class MarkAsReadTest extends TestCase
{
    use RefreshDatabase;

    // 運営からのお知らせを既読にした場合、通知詳細へ遷移することを確認する。
    public function test_admin_announcement_redirects_to_notification_detail(): void
    {
        // Arrange
        $student = User::factory()
            ->student()
            ->inProgress()
            ->create();

        $notification = $student->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => 'App\Notifications\Announcement\AdminAnnouncementNotification',
            'data' => [
                'notification_type' => 'admin_announcement',
                'title' => '運営からのお知らせ',
                'body' => 'お知らせ本文です。',
            ],
        ]);

        // Act
        $response = $this->actingAs($student)
            ->post(route('notifications.markAsRead', $notification));

        // Assert
        $response->assertRedirect(
            route('notifications.show', $notification),
        );

        $this->assertNotNull(
            $notification->fresh()->read_at,
        );
    }
}
