<?php

declare(strict_types=1);

namespace Tests\Feature\Http\MeetingPack;

use App\Models\MeetingPack;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArchiveTest extends TestCase
{
    use RefreshDatabase;

    // 管理者は公開中の面談パックをアーカイブできる
    public function test_admin_can_archive_published_meeting_pack(): void
    {
        $admin = User::factory()->admin()->create();
        $meetingPack = MeetingPack::factory()
            ->published()
            ->create([
                'created_by_user_id' => $admin->id,
                'updated_by_user_id' => $admin->id,
            ]);

        $response = $this->actingAs($admin)
            ->post(route('admin.meeting-packs.archive', $meetingPack));

        $response->assertRedirect();

        $this->assertDatabaseHas('meeting_packs', [
            'id' => $meetingPack->id,
            'status' => 'archived',
            'updated_by_user_id' => $admin->id,
        ]);
    }

    // 下書きの面談パックはアーカイブできない
    public function test_draft_meeting_pack_cannot_be_archived(): void
    {
        $admin = User::factory()->admin()->create();
        $meetingPack = MeetingPack::factory()
            ->draft()
            ->create();

        $response = $this->actingAs($admin)
            ->post(route('admin.meeting-packs.archive', $meetingPack));

        $response->assertRedirect();
        $response->assertSessionHas(
            'error',
            '公開中の面談パックのみアーカイブできます。',
        );

        $this->assertDatabaseHas('meeting_packs', [
            'id' => $meetingPack->id,
            'status' => 'draft',
        ]);
    }

    // アーカイブ済みの面談パックは再度アーカイブできない
    public function test_archived_meeting_pack_cannot_be_archived_again(): void
    {
        $admin = User::factory()->admin()->create();
        $meetingPack = MeetingPack::factory()
            ->archived()
            ->create();

        $response = $this->actingAs($admin)
            ->post(route('admin.meeting-packs.archive', $meetingPack));

        $response->assertRedirect();
        $response->assertSessionHas(
            'error',
            '公開中の面談パックのみアーカイブできます。',
        );

        $this->assertDatabaseHas('meeting_packs', [
            'id' => $meetingPack->id,
            'status' => 'archived',
        ]);
    }

    // コーチは面談パックをアーカイブできない
    public function test_coach_cannot_archive_meeting_pack(): void
    {
        $coach = User::factory()->coach()->create();
        $meetingPack = MeetingPack::factory()
            ->published()
            ->create();

        $response = $this->actingAs($coach)
            ->post(route('admin.meeting-packs.archive', $meetingPack));

        $response->assertForbidden();
    }

    // 学生は面談パックをアーカイブできない
    public function test_student_cannot_archive_meeting_pack(): void
    {
        $student = User::factory()->student()->create();
        $meetingPack = MeetingPack::factory()
            ->published()
            ->create();

        $response = $this->actingAs($student)
            ->post(route('admin.meeting-packs.archive', $meetingPack));

        $response->assertForbidden();
    }
}
