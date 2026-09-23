<?php

declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Models\Certification;
use App\Models\CertificationCoachAssignment;
use App\Models\Enrollment;
use App\Models\EnrollmentNote;
use App\Models\User;
use App\Policies\EnrollmentNotePolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class EnrollmentNotePolicyTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 担当資格のコーチはメモを閲覧・追加できる。
     */
    public function test_assigned_coach_can_view_and_create_notes(): void
    {
        $coach = User::factory()->coach()->create();
        $admin = User::factory()->admin()->create();
        $certification = Certification::factory()->published()->create();

        CertificationCoachAssignment::create([
            'id' => (string) Str::ulid(),
            'certification_id' => $certification->id,
            'user_id' => $coach->id,
            'assigned_by_user_id' => $admin->id,
            'assigned_at' => now(),
        ]);

        $enrollment = Enrollment::factory()
            ->for($certification)
            ->learning()
            ->create();

        $policy = new EnrollmentNotePolicy;

        $this->assertTrue($policy->viewAny($coach, $enrollment));
        $this->assertTrue($policy->create($coach, $enrollment));
    }

    /**
     * 担当外資格のコーチはメモを閲覧・追加できない。
     */
    public function test_unassigned_coach_cannot_view_and_create_notes(): void
    {
        $coach = User::factory()->coach()->create();
        $certification = Certification::factory()->published()->create();
        $enrollment = Enrollment::factory()
            ->for($certification)
            ->learning()
            ->create();

        $policy = new EnrollmentNotePolicy;

        $this->assertFalse($policy->viewAny($coach, $enrollment));
        $this->assertFalse($policy->create($coach, $enrollment));
    }

    /**
     * 自分のメモは担当資格であれば編集・削除できる。
     */
    public function test_assigned_coach_can_update_and_delete_own_note(): void
    {
        $coach = User::factory()->coach()->create();
        $admin = User::factory()->admin()->create();
        $certification = Certification::factory()->published()->create();

        CertificationCoachAssignment::create([
            'id' => (string) Str::ulid(),
            'certification_id' => $certification->id,
            'user_id' => $coach->id,
            'assigned_by_user_id' => $admin->id,
            'assigned_at' => now(),
        ]);

        $enrollment = Enrollment::factory()
            ->for($certification)
            ->learning()
            ->create();

        $note = EnrollmentNote::factory()
            ->for($enrollment)
            ->for($coach, 'author')
            ->create();

        $policy = new EnrollmentNotePolicy;

        $this->assertTrue($policy->update($coach, $note));
        $this->assertTrue($policy->delete($coach, $note));
    }

    /**
     * 他のコーチのメモは編集・削除できない。
     */
    public function test_coach_cannot_update_and_delete_other_coachs_note(): void
    {
        $coach = User::factory()->coach()->create();
        $otherCoach = User::factory()->coach()->create();
        $admin = User::factory()->admin()->create();
        $certification = Certification::factory()->published()->create();

        CertificationCoachAssignment::create([
            'id' => (string) Str::ulid(),
            'certification_id' => $certification->id,
            'user_id' => $coach->id,
            'assigned_by_user_id' => $admin->id,
            'assigned_at' => now(),
        ]);

        $enrollment = Enrollment::factory()
            ->for($certification)
            ->learning()
            ->create();

        $note = EnrollmentNote::factory()
            ->for($enrollment)
            ->for($otherCoach, 'author')
            ->create();

        $policy = new EnrollmentNotePolicy;

        $this->assertFalse($policy->update($coach, $note));
        $this->assertFalse($policy->delete($coach, $note));
    }

    /**
     * 担当から外れたコーチは過去に作成したメモも操作できない。
     */
    public function test_unassigned_coach_cannot_operate_own_note(): void
    {
        $coach = User::factory()->coach()->create();
        $admin = User::factory()->admin()->create();
        $certification = Certification::factory()->published()->create();

        $assignment = CertificationCoachAssignment::create([
            'id' => (string) Str::ulid(),
            'certification_id' => $certification->id,
            'user_id' => $coach->id,
            'assigned_by_user_id' => $admin->id,
            'assigned_at' => now(),
        ]);

        $enrollment = Enrollment::factory()
            ->for($certification)
            ->learning()
            ->create();

        $note = EnrollmentNote::factory()
            ->for($enrollment)
            ->for($coach, 'author')
            ->create();

        $assignment->update([
            'unassigned_at' => now(),
        ]);

        $policy = new EnrollmentNotePolicy;

        $this->assertFalse($policy->viewAny($coach, $enrollment));
        $this->assertFalse($policy->create($coach, $enrollment));
        $this->assertFalse($policy->update($coach, $note));
        $this->assertFalse($policy->delete($coach, $note));
    }

    /**
     * Adminはすべての受講登録のメモを操作できる。
     */
    public function test_admin_can_operate_all_notes(): void
    {
        $admin = User::factory()->admin()->create();
        $coach = User::factory()->coach()->create();
        $certification = Certification::factory()->published()->create();
        $enrollment = Enrollment::factory()
            ->for($certification)
            ->learning()
            ->create();
        $note = EnrollmentNote::factory()
            ->for($enrollment)
            ->for($coach, 'author')
            ->create();

        $policy = new EnrollmentNotePolicy;

        $this->assertTrue($policy->viewAny($admin, $enrollment));
        $this->assertTrue($policy->create($admin, $enrollment));
        $this->assertTrue($policy->update($admin, $note));
        $this->assertTrue($policy->delete($admin, $note));
    }

    /**
     * Studentはメモを閲覧・追加・編集・削除できない。
     */
    public function test_student_cannot_operate_notes(): void
    {
        $student = User::factory()->student()->create();
        $coach = User::factory()->coach()->create();
        $certification = Certification::factory()->published()->create();
        $enrollment = Enrollment::factory()
            ->for($student)
            ->for($certification)
            ->learning()
            ->create();
        $note = EnrollmentNote::factory()
            ->for($enrollment)
            ->for($coach, 'author')
            ->create();

        $policy = new EnrollmentNotePolicy;

        $this->assertFalse($policy->viewAny($student, $enrollment));
        $this->assertFalse($policy->create($student, $enrollment));
        $this->assertFalse($policy->update($student, $note));
        $this->assertFalse($policy->delete($student, $note));
    }
}
