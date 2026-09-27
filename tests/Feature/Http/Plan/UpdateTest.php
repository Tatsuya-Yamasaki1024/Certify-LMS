<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Plan;

use App\Models\Plan;
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
            'name' => '更新後のプラン',
            'description' => '更新後の説明文',
            'duration_days' => 180,
            'default_meeting_quota' => 24,
            'sort_order' => 20,
        ], $override);
    }

    // 管理者はプランを編集できる
    public function test_admin_can_update_plan(): void
    {
        $admin = User::factory()->admin()->create();

        $plan = Plan::factory()
            ->draft()
            ->create([
                'created_by_user_id' => $admin->id,
                'updated_by_user_id' => $admin->id,
            ]);

        $response = $this->actingAs($admin)
            ->patch(
                route('admin.plans.update', $plan),
                $this->payload(),
            );

        $response->assertRedirect();

        $this->assertDatabaseHas('plans', [
            'id' => $plan->id,
            'name' => '更新後のプラン',
            'description' => '更新後の説明文',
            'duration_days' => 180,
            'default_meeting_quota' => 24,
            'sort_order' => 20,
            'status' => 'draft',
            'updated_by_user_id' => $admin->id,
        ]);
    }

    // 編集してもプランの状態は変更されない
    public function test_update_does_not_change_status(): void
    {
        $admin = User::factory()->admin()->create();

        $plan = Plan::factory()
            ->published()
            ->create([
                'created_by_user_id' => $admin->id,
                'updated_by_user_id' => $admin->id,
            ]);

        $this->actingAs($admin)
            ->patch(
                route('admin.plans.update', $plan),
                $this->payload(),
            )
            ->assertRedirect();

        $this->assertDatabaseHas('plans', [
            'id' => $plan->id,
            'status' => 'published',
        ]);
    }

    // 必須項目がバリデーションされる
    public function test_required_fields_are_validated(): void
    {
        $admin = User::factory()->admin()->create();
        $plan = Plan::factory()->draft()->create();

        $this->actingAs($admin)
            ->patch(
                route('admin.plans.update', $plan),
                $this->payload(['name' => '']),
            )
            ->assertSessionHasErrors('name');

        $this->actingAs($admin)
            ->patch(
                route('admin.plans.update', $plan),
                $this->payload(['duration_days' => '']),
            )
            ->assertSessionHasErrors('duration_days');

        $this->actingAs($admin)
            ->patch(
                route('admin.plans.update', $plan),
                $this->payload(['default_meeting_quota' => '']),
            )
            ->assertSessionHasErrors('default_meeting_quota');
    }

    // 受講期間と面談回数の範囲がバリデーションされる
    public function test_numeric_ranges_are_validated(): void
    {
        $admin = User::factory()->admin()->create();
        $plan = Plan::factory()->draft()->create();

        $this->actingAs($admin)
            ->patch(
                route('admin.plans.update', $plan),
                $this->payload(['duration_days' => 0]),
            )
            ->assertSessionHasErrors('duration_days');

        $this->actingAs($admin)
            ->patch(
                route('admin.plans.update', $plan),
                $this->payload(['duration_days' => 3651]),
            )
            ->assertSessionHasErrors('duration_days');

        $this->actingAs($admin)
            ->patch(
                route('admin.plans.update', $plan),
                $this->payload(['default_meeting_quota' => -1]),
            )
            ->assertSessionHasErrors('default_meeting_quota');

        $this->actingAs($admin)
            ->patch(
                route('admin.plans.update', $plan),
                $this->payload(['default_meeting_quota' => 1001]),
            )
            ->assertSessionHasErrors('default_meeting_quota');
    }

    // コーチはプランを編集できない
    public function test_coach_cannot_update_plan(): void
    {
        $coach = User::factory()->coach()->create();
        $plan = Plan::factory()->draft()->create();

        $response = $this->actingAs($coach)
            ->patch(
                route('admin.plans.update', $plan),
                $this->payload(),
            );

        $response->assertForbidden();
    }

    // 学生はプランを編集できない
    public function test_student_cannot_update_plan(): void
    {
        $student = User::factory()->student()->create();
        $plan = Plan::factory()->draft()->create();

        $response = $this->actingAs($student)
            ->patch(
                route('admin.plans.update', $plan),
                $this->payload(),
            );

        $response->assertForbidden();
    }

    // 文字列の形式と最大文字数がバリデーションされる
    public function test_string_fields_are_validated(): void
    {
        $admin = User::factory()->admin()->create();
        $plan = Plan::factory()->create();

        $this->actingAs($admin)
            ->put(
                route('admin.plans.update', $plan),
                $this->payload([
                    'name' => str_repeat('あ', 101),
                ]),
            )
            ->assertSessionHasErrors('name');

        $this->actingAs($admin)
            ->put(
                route('admin.plans.update', $plan),
                $this->payload([
                    'name' => 123,
                ]),
            )
            ->assertSessionHasErrors('name');

        $this->actingAs($admin)
            ->put(
                route('admin.plans.update', $plan),
                $this->payload([
                    'description' => str_repeat('あ', 2001),
                ]),
            )
            ->assertSessionHasErrors('description');

        $this->actingAs($admin)
            ->put(
                route('admin.plans.update', $plan),
                $this->payload([
                    'description' => 123,
                ]),
            )
            ->assertSessionHasErrors('description');
    }

    // 数値項目の整数形式がバリデーションされる
    public function test_integer_fields_are_validated(): void
    {
        $admin = User::factory()->admin()->create();
        $plan = Plan::factory()->create();

        $this->actingAs($admin)
            ->put(
                route('admin.plans.update', $plan),
                $this->payload([
                    'duration_days' => '90日',
                ]),
            )
            ->assertSessionHasErrors('duration_days');

        $this->actingAs($admin)
            ->put(
                route('admin.plans.update', $plan),
                $this->payload([
                    'default_meeting_quota' => '12回',
                ]),
            )
            ->assertSessionHasErrors('default_meeting_quota');

        $this->actingAs($admin)
            ->put(
                route('admin.plans.update', $plan),
                $this->payload([
                    'sort_order' => '10番',
                ]),
            )
            ->assertSessionHasErrors('sort_order');
    }

    // sort_order の範囲がバリデーションされる
    public function test_sort_order_range_is_validated(): void
    {
        $admin = User::factory()->admin()->create();
        $plan = Plan::factory()->create();

        $this->actingAs($admin)
            ->put(
                route('admin.plans.update', $plan),
                $this->payload([
                    'sort_order' => -1,
                ]),
            )
            ->assertSessionHasErrors('sort_order');
    }
}
