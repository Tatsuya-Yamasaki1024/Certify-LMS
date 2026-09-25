<?php

declare(strict_types=1);

namespace Tests\Feature\Http\MeetingPack;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateTest extends TestCase
{
    use RefreshDatabase;

    // 管理者は面談パック作成画面を表示できる
    public function test_admin_can_view_meeting_pack_create(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)
            ->get(route('admin.meeting-packs.create'));

        $response->assertOk();
        $response->assertViewIs('meeting-pack.management.create');
    }

    // コーチは面談パック作成画面を表示できない
    public function test_coach_cannot_view_meeting_pack_create(): void
    {
        $coach = User::factory()->coach()->create();

        $response = $this->actingAs($coach)
            ->get(route('admin.meeting-packs.create'));

        $response->assertForbidden();
    }

    // 学生は面談パック作成画面を表示できない
    public function test_student_cannot_view_meeting_pack_create(): void
    {
        $student = User::factory()->student()->create();

        $response = $this->actingAs($student)
            ->get(route('admin.meeting-packs.create'));

        $response->assertForbidden();
    }
}
