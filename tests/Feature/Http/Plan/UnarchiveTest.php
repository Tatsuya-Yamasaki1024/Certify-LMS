<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Plan;

use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UnarchiveTest extends TestCase
{
    use RefreshDatabase;

    // 管理者はアーカイブ済みのプランを下書きに戻せる
    public function test_admin_can_unarchive_archived_plan(): void
    {
        $admin = User::factory()->admin()->create();

        $plan = Plan::factory()
            ->archived()
            ->create();

        $response = $this->actingAs($admin)
            ->post(route('admin.plans.unarchive', $plan));

        $response->assertRedirect();

        $this->assertDatabaseHas('plans', [
            'id' => $plan->id,
            'status' => 'draft',
        ]);
    }

    // 下書きのプランはアーカイブ解除できない
    public function test_draft_plan_cannot_be_unarchived(): void
    {
        $admin = User::factory()->admin()->create();

        $plan = Plan::factory()
            ->draft()
            ->create();

        $response = $this->actingAs($admin)
            ->post(route('admin.plans.unarchive', $plan));

        $response->assertRedirect();

        $this->assertDatabaseHas('plans', [
            'id' => $plan->id,
            'status' => 'draft',
        ]);
    }

    // 公開中のプランはアーカイブ解除できない
    public function test_published_plan_cannot_be_unarchived(): void
    {
        $admin = User::factory()->admin()->create();

        $plan = Plan::factory()
            ->published()
            ->create();

        $response = $this->actingAs($admin)
            ->post(route('admin.plans.unarchive', $plan));

        $response->assertRedirect();

        $this->assertDatabaseHas('plans', [
            'id' => $plan->id,
            'status' => 'published',
        ]);
    }

    // コーチはプランをアーカイブ解除できない
    public function test_coach_cannot_unarchive_plan(): void
    {
        $coach = User::factory()->coach()->create();

        $plan = Plan::factory()
            ->archived()
            ->create();

        $response = $this->actingAs($coach)
            ->post(route('admin.plans.unarchive', $plan));

        $response->assertForbidden();
    }

    // 学生はプランをアーカイブ解除できない
    public function test_student_cannot_unarchive_plan(): void
    {
        $student = User::factory()->student()->create();

        $plan = Plan::factory()
            ->archived()
            ->create();

        $response = $this->actingAs($student)
            ->post(route('admin.plans.unarchive', $plan));

        $response->assertForbidden();
    }
}
