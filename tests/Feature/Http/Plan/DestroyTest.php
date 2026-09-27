<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Plan;

use App\Models\Plan;
use App\Models\User;
use App\Models\UserPlanLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DestroyTest extends TestCase
{
    use RefreshDatabase;

    // 管理者は受講者と利用履歴がない下書きのプランを削除できる
    public function test_admin_can_delete_draft_plan_without_users_or_history(): void
    {
        $admin = User::factory()->admin()->create();

        $plan = Plan::factory()
            ->draft()
            ->create();

        $response = $this->actingAs($admin)
            ->delete(route('admin.plans.destroy', $plan));

        $response->assertRedirect();

        $this->assertDatabaseMissing('plans', [
            'id' => $plan->id,
        ]);
    }

    // 公開中のプランは削除できない
    public function test_published_plan_cannot_be_deleted(): void
    {
        $admin = User::factory()->admin()->create();

        $plan = Plan::factory()
            ->published()
            ->create();

        $response = $this->actingAs($admin)
            ->delete(route('admin.plans.destroy', $plan));

        $response->assertRedirect();
        $response->assertSessionHas(
            'error',
            '下書きのプランのみ削除できます。削除する場合は下書きに変更してください。',
        );

        $this->assertDatabaseHas('plans', [
            'id' => $plan->id,
            'status' => 'published',
        ]);
    }

    // アーカイブ済みのプランは削除できない
    public function test_archived_plan_cannot_be_deleted(): void
    {
        $admin = User::factory()->admin()->create();

        $plan = Plan::factory()
            ->archived()
            ->create();

        $response = $this->actingAs($admin)
            ->delete(route('admin.plans.destroy', $plan));

        $response->assertRedirect();
        $response->assertSessionHas(
            'error',
            '下書きのプランのみ削除できます。削除する場合は下書きに変更してください。',
        );

        $this->assertDatabaseHas('plans', [
            'id' => $plan->id,
            'status' => 'archived',
        ]);
    }

    // 受講者が紐づいている下書きのプランは削除できない
    public function test_draft_plan_with_users_cannot_be_deleted(): void
    {
        $admin = User::factory()->admin()->create();

        $plan = Plan::factory()
            ->draft()
            ->create();

        User::factory()->create([
            'plan_id' => $plan->id,
        ]);

        $response = $this->actingAs($admin)
            ->delete(route('admin.plans.destroy', $plan));

        $response->assertRedirect();
        $response->assertSessionHas(
            'error',
            '受講者が現在利用中、または過去に利用した履歴があるプランは削除できません。',
        );

        $this->assertDatabaseHas('plans', [
            'id' => $plan->id,
        ]);
    }

    // 利用履歴がある下書きのプランは削除できない
    public function test_draft_plan_with_history_cannot_be_deleted(): void
    {
        $admin = User::factory()->admin()->create();

        $plan = Plan::factory()
            ->draft()
            ->create();

        UserPlanLog::factory()->create([
            'plan_id' => $plan->id,
        ]);

        $response = $this->actingAs($admin)
            ->delete(route('admin.plans.destroy', $plan));

        $response->assertRedirect();
        $response->assertSessionHas(
            'error',
            '受講者が現在利用中、または過去に利用した履歴があるプランは削除できません。',
        );

        $this->assertDatabaseHas('plans', [
            'id' => $plan->id,
        ]);
    }

    // コーチはプランを削除できない
    public function test_coach_cannot_delete_plan(): void
    {
        $coach = User::factory()->coach()->create();

        $plan = Plan::factory()
            ->draft()
            ->create();

        $response = $this->actingAs($coach)
            ->delete(route('admin.plans.destroy', $plan));

        $response->assertForbidden();
    }

    // 学生はプランを削除できない
    public function test_student_cannot_delete_plan(): void
    {
        $student = User::factory()->student()->create();

        $plan = Plan::factory()
            ->draft()
            ->create();

        $response = $this->actingAs($student)
            ->delete(route('admin.plans.destroy', $plan));

        $response->assertForbidden();
    }
}
