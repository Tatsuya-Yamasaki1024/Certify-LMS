<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Plan;

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
            'name' => '新規プラン',
            'description' => '新規プランの説明です。',
            'duration_days' => 90,
            'default_meeting_quota' => 12,
            'sort_order' => 10,
        ], $override);
    }

    // 管理者はプランを下書きとして作成できる
    public function test_admin_can_create_plan_as_draft(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)
            ->post(
                route('admin.plans.store'),
                $this->payload(),
            );

        $response->assertRedirect();

        $this->assertDatabaseHas('plans', [
            'name' => '新規プラン',
            'description' => '新規プランの説明です。',
            'duration_days' => 90,
            'default_meeting_quota' => 12,
            'sort_order' => 10,
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
                route('admin.plans.store'),
                $this->payload(['name' => '']),
            )
            ->assertSessionHasErrors('name');

        $this->actingAs($admin)
            ->post(
                route('admin.plans.store'),
                $this->payload(['duration_days' => '']),
            )
            ->assertSessionHasErrors('duration_days');

        $this->actingAs($admin)
            ->post(
                route('admin.plans.store'),
                $this->payload(['default_meeting_quota' => '']),
            )
            ->assertSessionHasErrors('default_meeting_quota');
    }

    // 受講期間と面談回数の範囲がバリデーションされる
    public function test_numeric_ranges_are_validated(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(
                route('admin.plans.store'),
                $this->payload(['duration_days' => 0]),
            )
            ->assertSessionHasErrors('duration_days');

        $this->actingAs($admin)
            ->post(
                route('admin.plans.store'),
                $this->payload(['duration_days' => 3651]),
            )
            ->assertSessionHasErrors('duration_days');

        $this->actingAs($admin)
            ->post(
                route('admin.plans.store'),
                $this->payload(['default_meeting_quota' => -1]),
            )
            ->assertSessionHasErrors('default_meeting_quota');

        $this->actingAs($admin)
            ->post(
                route('admin.plans.store'),
                $this->payload(['default_meeting_quota' => 1001]),
            )
            ->assertSessionHasErrors('default_meeting_quota');
    }

    // コーチはプランを作成できない
    public function test_coach_cannot_create_plan(): void
    {
        $coach = User::factory()->coach()->create();

        $response = $this->actingAs($coach)
            ->post(
                route('admin.plans.store'),
                $this->payload(),
            );

        $response->assertForbidden();
    }

    // 学生はプランを作成できない
    public function test_student_cannot_create_plan(): void
    {
        $student = User::factory()->student()->create();

        $response = $this->actingAs($student)
            ->post(
                route('admin.plans.store'),
                $this->payload(),
            );

        $response->assertForbidden();
    }

    // 文字列の形式と最大文字数がバリデーションされる
    public function test_string_fields_are_validated(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('admin.plans.store'), $this->payload([
                'name' => str_repeat('あ', 101),
            ]))
            ->assertSessionHasErrors('name');

        $this->actingAs($admin)
            ->post(route('admin.plans.store'), $this->payload([
                'name' => 123,
            ]))
            ->assertSessionHasErrors('name');

        $this->actingAs($admin)
            ->post(route('admin.plans.store'), $this->payload([
                'description' => str_repeat('あ', 2001),
            ]))
            ->assertSessionHasErrors('description');

        $this->actingAs($admin)
            ->post(route('admin.plans.store'), $this->payload([
                'description' => 123,
            ]))
            ->assertSessionHasErrors('description');
    }

    // 数値項目の整数形式がバリデーションされる
    public function test_integer_fields_are_validated(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('admin.plans.store'), $this->payload([
                'duration_days' => '90日',
            ]))
            ->assertSessionHasErrors('duration_days');

        $this->actingAs($admin)
            ->post(route('admin.plans.store'), $this->payload([
                'default_meeting_quota' => '12回',
            ]))
            ->assertSessionHasErrors('default_meeting_quota');

        $this->actingAs($admin)
            ->post(route('admin.plans.store'), $this->payload([
                'sort_order' => '10番',
            ]))
            ->assertSessionHasErrors('sort_order');
    }

    // sort_order の範囲がバリデーションされる
    public function test_sort_order_range_is_validated(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('admin.plans.store'), $this->payload([
                'sort_order' => -1,
            ]))
            ->assertSessionHasErrors('sort_order');
    }
}
