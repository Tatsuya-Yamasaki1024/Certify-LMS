<?php

declare(strict_types=1);

namespace Tests\Feature\Http\MeetingPack;

use App\Models\MeetingPack;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UnarchiveTest extends TestCase
{
    use RefreshDatabase;

    // 管理者はアーカイブ済みの面談パックを下書きに戻せる
    public function test_admin_can_unarchive_archived_meeting_pack(): void
    {
        $admin = User::factory()->admin()->create();
        $meetingPack = MeetingPack::factory()
            ->archived()
            ->create([
                'created_by_user_id' => $admin->id,
                'updated_by_user_id' => $admin->id,
            ]);

        $response = $this->actingAs($admin)
            ->post(route('admin.meeting-packs.unarchive', $meetingPack));

        $response->assertRedirect();

        $this->assertDatabaseHas('meeting_packs', [
            'id' => $meetingPack->id,
            'status' => 'draft',
            'updated_by_user_id' => $admin->id,
        ]);
    }

    // 下書きの面談パックは下書きに戻せない
    public function test_draft_meeting_pack_cannot_be_unarchived(): void
    {
        $admin = User::factory()->admin()->create();
        $meetingPack = MeetingPack::factory()
            ->draft()
            ->create();

        $response = $this->actingAs($admin)
            ->post(route('admin.meeting-packs.unarchive', $meetingPack));

        $response->assertRedirect();
        $response->assertSessionHas(
            'error',
            'アーカイブ状態の面談パックのみ下書きに戻せます。',
        );

        $this->assertDatabaseHas('meeting_packs', [
            'id' => $meetingPack->id,
            'status' => 'draft',
        ]);
    }

    // 公開中の面談パックは下書きに戻せない
    public function test_published_meeting_pack_cannot_be_unarchived(): void
    {
        $admin = User::factory()->admin()->create();
        $meetingPack = MeetingPack::factory()
            ->published()
            ->create();

        $response = $this->actingAs($admin)
            ->post(route('admin.meeting-packs.unarchive', $meetingPack));

        $response->assertRedirect();
        $response->assertSessionHas(
            'error',
            'アーカイブ状態の面談パックのみ下書きに戻せます。',
        );

        $this->assertDatabaseHas('meeting_packs', [
            'id' => $meetingPack->id,
            'status' => 'published',
        ]);
    }

    // コーチは面談パックを下書きに戻せない
    public function test_coach_cannot_unarchive_meeting_pack(): void
    {
        $coach = User::factory()->coach()->create();
        $meetingPack = MeetingPack::factory()
            ->archived()
            ->create();

        $response = $this->actingAs($coach)
            ->post(route('admin.meeting-packs.unarchive', $meetingPack));

        $response->assertForbidden();
    }

    // 学生は面談パックを下書きに戻せない
    public function test_student_cannot_unarchive_meeting_pack(): void
    {
        $student = User::factory()->student()->create();
        $meetingPack = MeetingPack::factory()
            ->archived()
            ->create();

        $response = $this->actingAs($student)
            ->post(route('admin.meeting-packs.unarchive', $meetingPack));

        $response->assertForbidden();
    }
}
