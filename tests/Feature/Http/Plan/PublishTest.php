<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Plan;

use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublishTest extends TestCase
{
    use RefreshDatabase;

    // 管理者は下書きのプランを公開できる
    public function test_admin_can_publish_draft_plan(): void
    {
        $admin = User::factory()->admin()->create();

        $plan = Plan::factory()
            ->draft()
            ->create();

        $response = $this->actingAs($admin)
            ->post(route('admin.plans.publish', $plan));

        $response->assertRedirect();

        $this->assertDatabaseHas('plans', [
            'id' => $plan->id,
            'status' => 'published',
        ]);
    }

    // 公開中のプランは公開できない
    public function test_published_plan_cannot_be_published(): void
    {
        $admin = User::factory()->admin()->create();

        $plan = Plan::factory()
            ->published()
            ->create();

        $response = $this->actingAs($admin)
            ->post(route('admin.plans.publish', $plan));

        $response->assertRedirect();

        $this->assertDatabaseHas('plans', [
            'id' => $plan->id,
            'status' => 'published',
        ]);
    }

    // アーカイブ済みのプランは公開できない
    public function test_archived_plan_cannot_be_published(): void
    {
        $admin = User::factory()->admin()->create();

        $plan = Plan::factory()
            ->archived()
            ->create();

        $response = $this->actingAs($admin)
            ->post(route('admin.plans.publish', $plan));

        $response->assertRedirect();

        $this->assertDatabaseHas('plans', [
            'id' => $plan->id,
            'status' => 'archived',
        ]);
    }

    // コーチはプランを公開できない
    public function test_coach_cannot_publish_plan(): void
    {
        $coach = User::factory()->coach()->create();

        $plan = Plan::factory()
            ->draft()
            ->create();

        $response = $this->actingAs($coach)
            ->post(route('admin.plans.publish', $plan));

        $response->assertForbidden();
    }

    // 学生はプランを公開できない
    public function test_student_cannot_publish_plan(): void
    {
        $student = User::factory()->student()->create();

        $plan = Plan::factory()
            ->draft()
            ->create();

        $response = $this->actingAs($student)
            ->post(route('admin.plans.publish', $plan));

        $response->assertForbidden();
    }
}
