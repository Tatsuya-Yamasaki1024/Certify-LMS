<?php

declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Models\Enrollment;
use App\Models\EnrollmentGoal;
use App\Models\User;
use App\Policies\EnrollmentGoalPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EnrollmentGoalPolicyTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 受講中の本人は個人学習目標を操作できる。
     */
    public function test_student_can_operate_own_goal_while_learning(): void
    {
        $student = User::factory()->student()->create();
        $enrollment = Enrollment::factory()
            ->for($student)
            ->learning()
            ->create();
        $goal = EnrollmentGoal::factory()
            ->for($enrollment)
            ->create();
        $policy = new EnrollmentGoalPolicy;

        $this->assertTrue($policy->create($student, $enrollment));
        $this->assertTrue($policy->update($student, $goal));
        $this->assertTrue($policy->delete($student, $goal));
        $this->assertTrue($policy->markAchieved($student, $goal));
        $this->assertTrue($policy->unmarkAchieved($student, $goal));
    }

    /**
     * 他人の個人学習目標は操作できない。
     */
    public function test_student_cannot_operate_other_students_goal(): void
    {
        $student = User::factory()->student()->create();
        $other = User::factory()->student()->create();
        $enrollment = Enrollment::factory()
            ->for($other)
            ->learning()
            ->create();
        $goal = EnrollmentGoal::factory()
            ->for($enrollment)
            ->create();
        $policy = new EnrollmentGoalPolicy;

        $this->assertFalse($policy->create($student, $enrollment));
        $this->assertFalse($policy->update($student, $goal));
        $this->assertFalse($policy->delete($student, $goal));
        $this->assertFalse($policy->markAchieved($student, $goal));
        $this->assertFalse($policy->unmarkAchieved($student, $goal));
    }

    /**
     * 受講中ではない学生は個人学習目標を操作できない。
     */
    public function test_student_cannot_operate_goal_when_enrollment_is_not_learning(): void
    {
        $student = User::factory()->student()->create();
        $enrollment = Enrollment::factory()
            ->for($student)
            ->passed()
            ->create();
        $goal = EnrollmentGoal::factory()
            ->for($enrollment)
            ->create();
        $policy = new EnrollmentGoalPolicy;

        $this->assertFalse($policy->create($student, $enrollment));
        $this->assertFalse($policy->update($student, $goal));
        $this->assertFalse($policy->delete($student, $goal));
        $this->assertFalse($policy->markAchieved($student, $goal));
        $this->assertFalse($policy->unmarkAchieved($student, $goal));
    }

    /**
     * コーチとAdminは個人学習目標を操作できない。
     */
    public function test_coach_and_admin_cannot_operate_goal(): void
    {
        $student = User::factory()->student()->create();
        $coach = User::factory()->coach()->create();
        $admin = User::factory()->admin()->create();
        $enrollment = Enrollment::factory()
            ->for($student)
            ->learning()
            ->create();
        $goal = EnrollmentGoal::factory()
            ->for($enrollment)
            ->create();
        $policy = new EnrollmentGoalPolicy;

        $this->assertFalse($policy->create($coach, $enrollment));
        $this->assertFalse($policy->create($admin, $enrollment));
        $this->assertFalse($policy->update($coach, $goal));
        $this->assertFalse($policy->update($admin, $goal));
        $this->assertFalse($policy->delete($coach, $goal));
        $this->assertFalse($policy->delete($admin, $goal));
        $this->assertFalse($policy->markAchieved($coach, $goal));
        $this->assertFalse($policy->markAchieved($admin, $goal));
        $this->assertFalse($policy->unmarkAchieved($coach, $goal));
        $this->assertFalse($policy->unmarkAchieved($admin, $goal));
    }
}
