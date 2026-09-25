<?php

declare(strict_types=1);

namespace Tests\Feature\Http\MeetingPack;

use App\Models\MeetingPack;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EditTest extends TestCase
{
    use RefreshDatabase;

    // 管理者は面談パック編集画面を表示できる
    public function test_admin_can_view_meeting_pack_edit(): void
    {
        $admin = User::factory()->admin()->create();

        $meetingPack = MeetingPack::factory()
            ->draft()
            ->create();

        $response = $this->actingAs($admin)
            ->get(route('admin.meeting-packs.edit', $meetingPack));

        $response->assertOk();
        $response->assertViewIs('meeting-pack.management.edit');
        $response->assertSee($meetingPack->name);
    }

    // コーチは面談パック編集画面を表示できない
    public function test_coach_cannot_view_meeting_pack_edit(): void
    {
        $coach = User::factory()->coach()->create();
        $meetingPack = MeetingPack::factory()->draft()->create();

        $response = $this->actingAs($coach)
            ->get(route('admin.meeting-packs.edit', $meetingPack));

        $response->assertForbidden();
    }

    // 学生は面談パック編集画面を表示できない
    public function test_student_cannot_view_meeting_pack_edit(): void
    {
        $student = User::factory()->student()->create();
        $meetingPack = MeetingPack::factory()->draft()->create();

        $response = $this->actingAs($student)
            ->get(route('admin.meeting-packs.edit', $meetingPack));

        $response->assertForbidden();
    }
}
