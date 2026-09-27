<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Plan;

use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArchiveTest extends TestCase
{
    use RefreshDatabase;

    // 管理者は公開中のプランをアーカイブできる
    public function test_admin_can_archive_published_plan(): void
    {
        $admin = User::factory()->admin()->create();

        $plan = Plan::factory()
            ->published()
            ->create();

        $response = $this->actingAs($admin)
            ->post(route('admin.plans.archive', $plan));

        $response->assertRedirect();

        $this->assertDatabaseHas('plans', [
            'id' => $plan->id,
            'status' => 'archived',
        ]);
    }

    // 下書きのプランはアーカイブできない
    public function test_draft_plan_cannot_be_archived(): void
    {
        $admin = User::factory()->admin()->create();

        $plan = Plan::factory()
            ->draft()
            ->create();

        $response = $this->actingAs($admin)
            ->post(route('admin.plans.archive', $plan));

        $response->assertRedirect();

        $this->assertDatabaseHas('plans', [
            'id' => $plan->id,
            'status' => 'draft',
        ]);
    }

    // アーカイブ済みのプランは再度アーカイブできない
    public function test_archived_plan_cannot_be_archived(): void
    {
        $admin = User::factory()->admin()->create();

        $plan = Plan::factory()
            ->archived()
            ->create();

        $response = $this->actingAs($admin)
            ->post(route('admin.plans.archive', $plan));

        $response->assertRedirect();

        $this->assertDatabaseHas('plans', [
            'id' => $plan->id,
            'status' => 'archived',
        ]);
    }

    // コーチはプランをアーカイブできない
    public function test_coach_cannot_archive_plan(): void
    {
        $coach = User::factory()->coach()->create();

        $plan = Plan::factory()
            ->published()
            ->create();

        $response = $this->actingAs($coach)
            ->post(route('admin.plans.archive', $plan));

        $response->assertForbidden();
    }

    // 学生はプランをアーカイブできない
    public function test_student_cannot_archive_plan(): void
    {
        $student = User::factory()->student()->create();

        $plan = Plan::factory()
            ->published()
            ->create();

        $response = $this->actingAs($student)
            ->post(route('admin.plans.archive', $plan));

        $response->assertForbidden();
    }
}
