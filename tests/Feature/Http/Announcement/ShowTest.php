<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Announcement;

use App\Models\Announcement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShowTest extends TestCase
{
    use RefreshDatabase;

    // 管理者がお知らせ詳細を表示できることを確認する。
    public function test_admin_can_view_announcement_detail(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();

        $announcement = Announcement::create([
            'title' => '運営からのお知らせ',
            'body' => 'お知らせ本文です。',
            'target_type' => 'all_students',
            'created_by_user_id' => $admin->id,
        ]);

        // Act
        $response = $this->actingAs($admin)
            ->get(route('admin.announcements.show', $announcement));

        // Assert
        $response->assertOk();
        $response->assertViewIs('announcement.management.show');
        $response->assertViewHas('announcement', $announcement);
        $response->assertSee('運営からのお知らせ');
        $response->assertSee('お知らせ本文です。');
    }

    // コーチがお知らせ詳細にアクセスできないことを確認する。
    public function test_coach_cannot_view_announcement_detail(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $coach = User::factory()->coach()->create();

        $announcement = Announcement::create([
            'title' => '運営からのお知らせ',
            'body' => 'お知らせ本文です。',
            'target_type' => 'all_students',
            'created_by_user_id' => $admin->id,
        ]);

        // Act
        $response = $this->actingAs($coach)
            ->get(route('admin.announcements.show', $announcement));

        // Assert
        $response->assertForbidden();
    }

    // 受講生がお知らせ詳細にアクセスできないことを確認する。
    public function test_student_cannot_view_announcement_detail(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $student = User::factory()->student()->create();

        $announcement = Announcement::create([
            'title' => '運営からのお知らせ',
            'body' => 'お知らせ本文です。',
            'target_type' => 'all_students',
            'created_by_user_id' => $admin->id,
        ]);

        // Act
        $response = $this->actingAs($student)
            ->get(route('admin.announcements.show', $announcement));

        // Assert
        $response->assertForbidden();
    }
}
