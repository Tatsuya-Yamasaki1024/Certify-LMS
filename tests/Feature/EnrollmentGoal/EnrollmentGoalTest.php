<?php

declare(strict_types=1);

namespace Tests\Feature\EnrollmentGoal;

use App\Models\Enrollment;
use App\Models\EnrollmentGoal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EnrollmentGoalTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 受講中の学生は個人学習目標を作成できる。
     */
    public function test_student_can_create_goal(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()
            ->for($student)
            ->learning()
            ->create();

        $response = $this->actingAs($student)->post(
            route('enrollments.goals.store', $enrollment),
            [
                'title' => 'Laravelの理解を深める',
                'description' => 'Laravelの基本機能を理解する。',
                'target_date' => '2026-10-01',
            ],
        );

        $response->assertRedirect(route('enrollments.show', $enrollment));

        $this->assertDatabaseHas('enrollment_goals', [
            'enrollment_id' => $enrollment->id,
            'title' => 'Laravelの理解を深める',
            'description' => 'Laravelの基本機能を理解する。',
            'target_date' => '2026-10-01',
            'achieved_at' => null,
        ]);
    }

    /**
     * 目標期日なしで個人学習目標を作成できる。
     */
    public function test_student_can_create_goal_without_target_date(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()
            ->for($student)
            ->learning()
            ->create();

        $response = $this->actingAs($student)->post(
            route('enrollments.goals.store', $enrollment),
            [
                'title' => '目標期日なしの目標',
                'description' => null,
                'target_date' => null,
            ],
        );

        $response->assertRedirect(route('enrollments.show', $enrollment));

        $this->assertDatabaseHas('enrollment_goals', [
            'enrollment_id' => $enrollment->id,
            'title' => '目標期日なしの目標',
            'target_date' => null,
        ]);
    }

    /**
     * 受講中の学生は個人学習目標を更新できる。
     */
    public function test_student_can_update_goal(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()
            ->for($student)
            ->learning()
            ->create();
        $goal = EnrollmentGoal::factory()
            ->for($enrollment)
            ->create();

        $response = $this->actingAs($student)->patch(
            route('enrollment-goals.update', $goal),
            [
                'title' => '更新した目標',
                'description' => '更新した説明',
                'target_date' => '2026-11-01',
            ],
        );

        $response->assertRedirect(route('enrollments.show', $enrollment));

        $this->assertDatabaseHas('enrollment_goals', [
            'id' => $goal->id,
            'title' => '更新した目標',
            'description' => '更新した説明',
            'target_date' => '2026-11-01',
        ]);
    }

    /**
     * 個人学習目標を達成済み・未達成に変更できる。
     */
    public function test_student_can_mark_and_unmark_goal_as_achieved(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()
            ->for($student)
            ->learning()
            ->create();
        $goal = EnrollmentGoal::factory()
            ->for($enrollment)
            ->create();

        $response = $this->actingAs($student)->post(
            route('enrollment-goals.markAchieved', $goal),
        );

        $response->assertRedirect(route('enrollments.show', $enrollment));

        $this->assertNotNull($goal->fresh()->achieved_at);

        $response = $this->actingAs($student)->delete(
            route('enrollment-goals.unmarkAchieved', $goal),
        );

        $response->assertRedirect(route('enrollments.show', $enrollment));

        $this->assertNull($goal->fresh()->achieved_at);
    }

    /**
     * 受講中の学生は個人学習目標を削除できる。
     */
    public function test_student_can_delete_goal(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()
            ->for($student)
            ->learning()
            ->create();
        $goal = EnrollmentGoal::factory()
            ->for($enrollment)
            ->create();

        $response = $this->actingAs($student)->delete(
            route('enrollment-goals.destroy', $goal),
        );

        $response->assertRedirect(route('enrollments.show', $enrollment));

        $this->assertDatabaseMissing('enrollment_goals', [
            'id' => $goal->id,
        ]);
    }
}
