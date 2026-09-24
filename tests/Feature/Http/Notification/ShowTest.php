<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Notification;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShowTest extends TestCase
{
    use RefreshDatabase;

    // 受講生が自分の通知詳細を表示できることを確認する。
    public function test_student_can_view_own_notification(): void
    {
        // Arrange
        $student = User::factory()->student()->inProgress()->create();

        $notification = $student->notifications()->create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'type' => 'App\Notifications\Announcement\AdminAnnouncementNotification',
            'data' => [
                'notification_type' => 'admin_announcement',
                'title' => '運営からのお知らせ',
                'body' => 'お知らせ本文です。',
            ],
        ]);

        // Act
        $response = $this->actingAs($student)
            ->get(route('notifications.show', $notification));

        // Assert
        $response->assertOk();
        $response->assertViewIs('notifications.show');
        $response->assertViewHas('notification');
        $response->assertSee('運営からのお知らせ');
        $response->assertSee('お知らせ本文です。');
    }

    // 通知詳細を表示した際に通知が既読になることを確認する。
    public function test_viewing_notification_marks_it_as_read(): void
    {
        // Arrange
        $student = User::factory()->student()->inProgress()->create();

        $notification = $student->notifications()->create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'type' => 'App\Notifications\Announcement\AdminAnnouncementNotification',
            'data' => [
                'notification_type' => 'admin_announcement',
                'title' => '運営からのお知らせ',
                'body' => 'お知らせ本文です。',
            ],
        ]);

        // Act
        $this->actingAs($student)
            ->get(route('notifications.show', $notification));

        // Assert
        $this->assertNotNull(
            $notification->fresh()->read_at,
        );
    }

    // 他の受講生の通知を表示できないことを確認する。
    public function test_student_cannot_view_another_students_notification(): void
    {
        // Arrange
        $student = User::factory()->student()->inProgress()->create();
        $otherStudent = User::factory()->student()->inProgress()->create();

        $notification = $otherStudent->notifications()->create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'type' => 'App\Notifications\Announcement\AdminAnnouncementNotification',
            'data' => [
                'notification_type' => 'admin_announcement',
                'title' => '他の受講生向けのお知らせ',
                'body' => '他の受講生には見せない本文です。',
            ],
        ]);

        // Act
        $response = $this->actingAs($student)
            ->get(route('notifications.show', $notification));

        // Assert
        $response->assertNotFound();
    }
}
