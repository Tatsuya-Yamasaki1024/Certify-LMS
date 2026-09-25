<?php

declare(strict_types=1);

namespace Tests\Feature\Http\MeetingPack;

use App\Models\MeetingPack;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpdateTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param array<string, mixed> $override
     *
     * @return array<string, mixed>
     */
    private function payload(array $override = []): array
    {
        return array_merge([
            'name' => '更新後の面談パック',
            'description' => '更新後の説明文',
            'meeting_count' => 5,
            'price' => 15000,
            'stripe_price_id' => null,
            'sort_order' => 20,
        ], $override);
    }

    // 管理者は面談パックを編集できる
    public function test_admin_can_update_meeting_pack(): void
    {
        $admin = User::factory()->admin()->create();
        $meetingPack = MeetingPack::factory()
            ->draft()
            ->create([
                'created_by_user_id' => $admin->id,
                'updated_by_user_id' => $admin->id,
            ]);

        $response = $this->actingAs($admin)
            ->patch(
                route('admin.meeting-packs.update', $meetingPack),
                $this->payload(),
            );

        $response->assertRedirect();

        $this->assertDatabaseHas('meeting_packs', [
            'id' => $meetingPack->id,
            'name' => '更新後の面談パック',
            'description' => '更新後の説明文',
            'meeting_count' => 5,
            'price' => 15000,
            'sort_order' => 20,
            'status' => 'draft',
            'updated_by_user_id' => $admin->id,
        ]);
    }

    // 編集しても面談パックの状態は変更されない
    public function test_update_does_not_change_status(): void
    {
        $admin = User::factory()->admin()->create();
        $meetingPack = MeetingPack::factory()
            ->published()
            ->create([
                'created_by_user_id' => $admin->id,
                'updated_by_user_id' => $admin->id,
            ]);

        $this->actingAs($admin)
            ->patch(
                route('admin.meeting-packs.update', $meetingPack),
                $this->payload(),
            )
            ->assertRedirect();

        $this->assertDatabaseHas('meeting_packs', [
            'id' => $meetingPack->id,
            'status' => 'published',
        ]);
    }

    // 必須項目がバリデーションされる
    public function test_required_fields_are_validated(): void
    {
        $admin = User::factory()->admin()->create();
        $meetingPack = MeetingPack::factory()
            ->draft()
            ->create();

        $this->actingAs($admin)
            ->patch(
                route('admin.meeting-packs.update', $meetingPack),
                $this->payload(['name' => '']),
            )
            ->assertSessionHasErrors('name');

        $this->actingAs($admin)
            ->patch(
                route('admin.meeting-packs.update', $meetingPack),
                $this->payload(['meeting_count' => '']),
            )
            ->assertSessionHasErrors('meeting_count');

        $this->actingAs($admin)
            ->patch(
                route('admin.meeting-packs.update', $meetingPack),
                $this->payload(['price' => '']),
            )
            ->assertSessionHasErrors('price');
    }

    // コーチは面談パックを編集できない
    public function test_coach_cannot_update_meeting_pack(): void
    {
        $coach = User::factory()->coach()->create();
        $meetingPack = MeetingPack::factory()->draft()->create();

        $response = $this->actingAs($coach)
            ->patch(
                route('admin.meeting-packs.update', $meetingPack),
                $this->payload(),
            );

        $response->assertForbidden();
    }

    // 学生は面談パックを編集できない
    public function test_student_cannot_update_meeting_pack(): void
    {
        $student = User::factory()->student()->create();
        $meetingPack = MeetingPack::factory()->draft()->create();

        $response = $this->actingAs($student)
            ->patch(
                route('admin.meeting-packs.update', $meetingPack),
                $this->payload(),
            );

        $response->assertForbidden();
    }
}
