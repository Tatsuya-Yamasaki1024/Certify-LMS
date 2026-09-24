<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Announcement;

use App\Enums\AnnouncementTargetType;
use App\Models\Announcement;
use App\Models\User;
use App\Notifications\Announcement\AdminAnnouncementNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class StoreTest extends TestCase
{
    use RefreshDatabase;

    // 管理者がお知らせを作成して配信できることを確認する。
    public function test_admin_can_create_and_dispatch_announcement(): void
    {
        // Arrange
        Notification::fake();

        $admin = User::factory()->admin()->create();
        $student = User::factory()
            ->student()
            ->inProgress()
            ->create();

        // Act
        $response = $this->actingAs($admin)
            ->post(route('admin.announcements.store'), [
                'title' => '運営からのお知らせ',
                'body' => 'お知らせ本文です。',
                'target_type' => AnnouncementTargetType::AllStudents->value,
            ]);

        // Assert
        $announcement = Announcement::query()
            ->where('title', '運営からのお知らせ')
            ->firstOrFail();

        $response->assertRedirect(
            route('admin.announcements.show', $announcement),
        );
        $response->assertSessionHas('success', 'お知らせを配信しました。');

        $this->assertDatabaseHas('announcements', [
            'id' => $announcement->id,
            'title' => '運営からのお知らせ',
            'body' => 'お知らせ本文です。',
            'target_type' => AnnouncementTargetType::AllStudents->value,
            'created_by_user_id' => $admin->id,
            'dispatched_count' => 1,
        ]);

        Notification::assertSentTo(
            $student,
            AdminAnnouncementNotification::class,
        );
    }

    // コーチがお知らせを作成・配信できないことを確認する。
    public function test_coach_cannot_create_announcement(): void
    {
        // Arrange
        $coach = User::factory()->coach()->create();

        // Act
        $response = $this->actingAs($coach)
            ->post(route('admin.announcements.store'), [
                'title' => 'お知らせ',
                'body' => '本文です。',
                'target_type' => AnnouncementTargetType::AllStudents->value,
            ]);

        // Assert
        $response->assertForbidden();

        $this->assertDatabaseCount('announcements', 0);
    }

    // 受講生がお知らせを作成・配信できないことを確認する。
    public function test_student_cannot_create_announcement(): void
    {
        // Arrange
        $student = User::factory()->student()->create();

        // Act
        $response = $this->actingAs($student)
            ->post(route('admin.announcements.store'), [
                'title' => 'お知らせ',
                'body' => '本文です。',
                'target_type' => AnnouncementTargetType::AllStudents->value,
            ]);

        // Assert
        $response->assertForbidden();

        $this->assertDatabaseCount('announcements', 0);
    }
}
