<?php

declare(strict_types=1);

namespace Tests\Feature\Http\MeetingPack;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StoreTest extends TestCase
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
            'name' => '新規面談パック',
            'description' => '説明文',
            'meeting_count' => 3,
            'price' => 9000,
            'stripe_price_id' => null,
            'sort_order' => 10,
        ], $override);
    }

    // 管理者は面談パックを下書きとして作成できる
    public function test_admin_can_create_meeting_pack_as_draft(): void
    {
        $admin = User::factory()->admin()->create();
        $payload = $this->payload();

        $response = $this->actingAs($admin)
            ->post(route('admin.meeting-packs.store'), $payload);

        $response->assertRedirect();

        $this->assertDatabaseHas('meeting_packs', [
            'name' => '新規面談パック',
            'status' => 'draft',
            'created_by_user_id' => $admin->id,
            'updated_by_user_id' => $admin->id,
        ]);
    }

    // 必須項目がバリデーションされる
    public function test_required_fields_are_validated(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(
                route('admin.meeting-packs.store'),
                $this->payload(['name' => '']),
            )
            ->assertSessionHasErrors('name');

        $this->actingAs($admin)
            ->post(
                route('admin.meeting-packs.store'),
                $this->payload(['meeting_count' => '']),
            )
            ->assertSessionHasErrors('meeting_count');

        $this->actingAs($admin)
            ->post(
                route('admin.meeting-packs.store'),
                $this->payload(['price' => '']),
            )
            ->assertSessionHasErrors('price');
    }

    // 面談回数と価格の範囲がバリデーションされる
    public function test_numeric_ranges_are_validated(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(
                route('admin.meeting-packs.store'),
                $this->payload(['meeting_count' => 0]),
            )
            ->assertSessionHasErrors('meeting_count');

        $this->actingAs($admin)
            ->post(
                route('admin.meeting-packs.store'),
                $this->payload(['meeting_count' => 101]),
            )
            ->assertSessionHasErrors('meeting_count');

        $this->actingAs($admin)
            ->post(
                route('admin.meeting-packs.store'),
                $this->payload(['price' => -1]),
            )
            ->assertSessionHasErrors('price');

        $this->actingAs($admin)
            ->post(
                route('admin.meeting-packs.store'),
                $this->payload(['price' => 1000001]),
            )
            ->assertSessionHasErrors('price');
    }

    // コーチは面談パックを作成できない
    public function test_coach_cannot_create_meeting_pack(): void
    {
        $coach = User::factory()->coach()->create();

        $response = $this->actingAs($coach)
            ->post(
                route('admin.meeting-packs.store'),
                $this->payload(),
            );

        $response->assertForbidden();
    }

    // 学生は面談パックを作成できない
    public function test_student_cannot_create_meeting_pack(): void
    {
        $student = User::factory()->student()->create();

        $response = $this->actingAs($student)
            ->post(
                route('admin.meeting-packs.store'),
                $this->payload(),
            );

        $response->assertForbidden();
    }
}
