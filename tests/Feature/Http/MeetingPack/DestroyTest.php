<?php

declare(strict_types=1);

namespace Tests\Feature\Http\MeetingPack;

use App\Models\MeetingPack;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DestroyTest extends TestCase
{
    use RefreshDatabase;

    // 管理者は下書きの面談パックを削除できる
    public function test_admin_can_delete_draft_meeting_pack(): void
    {
        $admin = User::factory()->admin()->create();
        $meetingPack = MeetingPack::factory()
            ->draft()
            ->create();

        $response = $this->actingAs($admin)
            ->delete(route('admin.meeting-packs.destroy', $meetingPack));

        $response->assertRedirect();

        $this->assertDatabaseMissing('meeting_packs', [
            'id' => $meetingPack->id,
        ]);
    }

    // 管理者はアーカイブ済みの面談パックを削除できる
    public function test_admin_can_delete_archived_meeting_pack(): void
    {
        $admin = User::factory()->admin()->create();
        $meetingPack = MeetingPack::factory()
            ->archived()
            ->create();

        $response = $this->actingAs($admin)
            ->delete(route('admin.meeting-packs.destroy', $meetingPack));

        $response->assertRedirect();

        $this->assertDatabaseMissing('meeting_packs', [
            'id' => $meetingPack->id,
        ]);
    }

    // 公開中の面談パックは削除できない
    public function test_published_meeting_pack_cannot_be_deleted(): void
    {
        $admin = User::factory()->admin()->create();
        $meetingPack = MeetingPack::factory()
            ->published()
            ->create();

        $response = $this->actingAs($admin)
            ->delete(route('admin.meeting-packs.destroy', $meetingPack));

        $response->assertRedirect();
        $response->assertSessionHas(
            'error',
            '公開中、または購入履歴がある面談パックは削除できません。',
        );

        $this->assertDatabaseHas('meeting_packs', [
            'id' => $meetingPack->id,
            'status' => 'published',
        ]);
    }

    // コーチは面談パックを削除できない
    public function test_coach_cannot_delete_meeting_pack(): void
    {
        $coach = User::factory()->coach()->create();
        $meetingPack = MeetingPack::factory()
            ->draft()
            ->create();

        $response = $this->actingAs($coach)
            ->delete(route('admin.meeting-packs.destroy', $meetingPack));

        $response->assertForbidden();
    }

    // 学生は面談パックを削除できない
    public function test_student_cannot_delete_meeting_pack(): void
    {
        $student = User::factory()->student()->create();
        $meetingPack = MeetingPack::factory()
            ->draft()
            ->create();

        $response = $this->actingAs($student)
            ->delete(route('admin.meeting-packs.destroy', $meetingPack));

        $response->assertForbidden();
    }
}
