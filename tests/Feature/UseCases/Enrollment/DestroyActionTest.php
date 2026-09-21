<?php

declare(strict_types=1);

namespace Tests\Feature\UseCases\Enrollment;

use App\Models\Enrollment;
use App\Models\EnrollmentGoal;
use App\Models\User;
use App\UseCases\Enrollment\DestroyAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Enrollment削除時の個人学習目標の物理削除を検証する。
 */
class DestroyActionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Enrollmentの削除時に紐づく個人学習目標も物理削除される。
     */
    public function test_deleting_enrollment_physically_deletes_goals(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()
            ->for($student)
            ->learning()
            ->create();
        $goal = EnrollmentGoal::factory()
            ->for($enrollment)
            ->create();

        app(DestroyAction::class)($enrollment);

        $this->assertSoftDeleted('enrollments', [
            'id' => $enrollment->id,
        ]);

        $this->assertDatabaseMissing('enrollment_goals', [
            'id' => $goal->id,
        ]);
    }
}
