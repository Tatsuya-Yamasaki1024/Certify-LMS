<?php

declare(strict_types=1);

namespace Tests\Feature\Http\MeetingPack;

use App\Models\MeetingPack;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublishTest extends TestCase
{
    use RefreshDatabase;

    // 管理者は下書きの面談パックを公開できる
    public function test_admin_can_publish_draft_meeting_pack(): void
    {
        $admin = User::factory()->admin()->create();
        $meetingPack = MeetingPack::factory()
            ->draft()
            ->create([
                'created_by_user_id' => $admin->id,
                'updated_by_user_id' => $admin->id,
            ]);

        $response = $this->actingAs($admin)
            ->post(route('admin.meeting-packs.publish', $meetingPack));

        $response->assertRedirect();

        $this->assertDatabaseHas('meeting_packs', [
            'id' => $meetingPack->id,
            'status' => 'published',
            'updated_by_user_id' => $admin->id,
        ]);
    }

    // 公開中の面談パックは再度公開できない
    public function test_published_meeting_pack_cannot_be_published_again(): void
    {
        $admin = User::factory()->admin()->create();
        $meetingPack = MeetingPack::factory()
            ->published()
            ->create();

        $response = $this->actingAs($admin)
            ->post(route('admin.meeting-packs.publish', $meetingPack));

        $response->assertRedirect();
        $response->assertSessionHas(
            'error',
            '下書き状態の面談パックのみ公開できます。',
        );

        $this->assertDatabaseHas('meeting_packs', [
            'id' => $meetingPack->id,
            'status' => 'published',
        ]);
    }

    // アーカイブ済みの面談パックは公開できない
    public function test_archived_meeting_pack_cannot_be_published(): void
    {
        $admin = User::factory()->admin()->create();
        $meetingPack = MeetingPack::factory()
            ->archived()
            ->create();

        $response = $this->actingAs($admin)
            ->post(route('admin.meeting-packs.publish', $meetingPack));

        $response->assertRedirect();
        $response->assertSessionHas(
            'error',
            '下書き状態の面談パックのみ公開できます。',
        );

        $this->assertDatabaseHas('meeting_packs', [
            'id' => $meetingPack->id,
            'status' => 'archived',
        ]);
    }

    // コーチは面談パックを公開できない
    public function test_coach_cannot_publish_meeting_pack(): void
    {
        $coach = User::factory()->coach()->create();
        $meetingPack = MeetingPack::factory()
            ->draft()
            ->create();

        $response = $this->actingAs($coach)
            ->post(route('admin.meeting-packs.publish', $meetingPack));

        $response->assertForbidden();
    }

    // 学生は面談パックを公開できない
    public function test_student_cannot_publish_meeting_pack(): void
    {
        $student = User::factory()->student()->create();
        $meetingPack = MeetingPack::factory()
            ->draft()
            ->create();

        $response = $this->actingAs($student)
            ->post(route('admin.meeting-packs.publish', $meetingPack));

        $response->assertForbidden();
    }
}
