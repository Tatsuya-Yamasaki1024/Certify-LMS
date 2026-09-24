<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Announcement;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateTest extends TestCase
{
    use RefreshDatabase;

    // 管理者がお知らせ作成画面を表示できることを確認する。
    public function test_admin_can_view_announcement_create_form(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();

        // Act
        $response = $this->actingAs($admin)
            ->get(route('admin.announcements.create'));

        // Assert
        $response->assertOk();
        $response->assertViewIs('announcement.management.create');
        $response->assertViewHas('certifications');
        $response->assertViewHas('students');
    }

    // コーチがお知らせ作成画面にアクセスできないことを確認する。
    public function test_coach_cannot_access_announcement_create_form(): void
    {
        // Arrange
        $coach = User::factory()->coach()->create();

        // Act
        $response = $this->actingAs($coach)
            ->get(route('admin.announcements.create'));

        // Assert
        $response->assertForbidden();
    }

    // 受講生がお知らせ作成画面にアクセスできないことを確認する。
    public function test_student_cannot_access_announcement_create_form(): void
    {
        // Arrange
        $student = User::factory()->student()->create();

        // Act
        $response = $this->actingAs($student)
            ->get(route('admin.announcements.create'));

        // Assert
        $response->assertForbidden();
    }
}
