<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Announcement;

use App\Models\Announcement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IndexTest extends TestCase
{
    use RefreshDatabase;

    // 管理者がお知らせ一覧を表示できることを確認する。
    public function test_admin_can_view_announcement_list(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        Announcement::create([
            'title' => '運営からのお知らせ',
            'body' => 'お知らせ本文です。',
            'target_type' => 'all_students',
            'created_by_user_id' => $admin->id,
        ]);

        // Act
        $response = $this->actingAs($admin)
            ->get(route('admin.announcements.index'));

        // Assert
        $response->assertOk();
        $response->assertViewIs('announcement.management.index');
        $response->assertViewHas('announcements');
        $response->assertSee('運営からのお知らせ');
    }

    // コーチがお知らせ管理画面にアクセスできないことを確認する。
    public function test_coach_cannot_access_announcement_list(): void
    {
        // Arrange
        $coach = User::factory()->coach()->create();

        // Act
        $response = $this->actingAs($coach)
            ->get(route('admin.announcements.index'));

        // Assert
        $response->assertForbidden();
    }

    // 受講生がお知らせ管理画面にアクセスできないことを確認する。
    public function test_student_cannot_access_announcement_list(): void
    {
        // Arrange
        $student = User::factory()->student()->create();

        // Act
        $response = $this->actingAs($student)
            ->get(route('admin.announcements.index'));

        // Assert
        $response->assertForbidden();
    }
}
