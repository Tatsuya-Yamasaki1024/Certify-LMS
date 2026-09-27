<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Plan;

use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShowTest extends TestCase
{
    use RefreshDatabase;

    // 管理者はプラン詳細を表示できる
    public function test_admin_can_view_plan_show(): void
    {
        $admin = User::factory()->admin()->create();
        $plan = Plan::factory()->create();

        $response = $this->actingAs($admin)
            ->get(route('admin.plans.show', $plan));

        $response->assertOk();
        $response->assertViewIs('plan.management.show');
    }

    // プランの基本情報が表示される
    public function test_plan_basic_information_is_displayed(): void
    {
        $admin = User::factory()->admin()->create();

        $plan = Plan::factory()->create([
            'name' => 'プレミアムプラン',
            'description' => 'プレミアムプランの説明です。',
            'duration_days' => 90,
            'default_meeting_quota' => 12,
        ]);

        $response = $this->actingAs($admin)
            ->get(route('admin.plans.show', $plan));

        $response->assertOk();
        $response->assertSee($plan->name);
        $response->assertSee($plan->description);
        $response->assertSee('90');
        $response->assertSee('12');
    }

    // プランに紐づいた受講者が表示される
    public function test_linked_users_are_displayed(): void
    {
        $admin = User::factory()->admin()->create();
        $plan = Plan::factory()->create();

        $users = User::factory()->count(2)->create([
            'plan_id' => $plan->id,
        ]);

        $response = $this->actingAs($admin)
            ->get(route('admin.plans.show', $plan));

        $response->assertOk();

        foreach ($users as $user) {
            $response->assertSee($user->name);
            $response->assertSee($user->email);
        }

        $response->assertSee('2');
    }

    // 作成者・最終更新者・作成日時が表示される
    public function test_plan_metadata_is_displayed(): void
    {
        $admin = User::factory()->admin()->create([
            'name' => '作成者',
        ]);

        $updater = User::factory()->admin()->create([
            'name' => '最終更新者',
        ]);

        $plan = Plan::factory()->create([
            'created_by_user_id' => $admin->id,
            'updated_by_user_id' => $updater->id,
        ]);

        $response = $this->actingAs($admin)
            ->get(route('admin.plans.show', $plan));

        $response->assertOk();
        $response->assertSee($admin->name);
        $response->assertSee($updater->name);
        $response->assertSee($plan->created_at->format('Y-m-d H:i'));
    }

    // コーチはプラン詳細を表示できない
    public function test_coach_cannot_view_plan_show(): void
    {
        $coach = User::factory()->coach()->create();
        $plan = Plan::factory()->create();

        $response = $this->actingAs($coach)
            ->get(route('admin.plans.show', $plan));

        $response->assertForbidden();
    }

    // 学生はプラン詳細を表示できない
    public function test_student_cannot_view_plan_show(): void
    {
        $student = User::factory()->student()->create();
        $plan = Plan::factory()->create();

        $response = $this->actingAs($student)
            ->get(route('admin.plans.show', $plan));

        $response->assertForbidden();
    }
}
