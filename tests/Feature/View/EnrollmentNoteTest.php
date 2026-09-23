<?php

declare(strict_types=1);

namespace Tests\Feature\View;

use App\Models\Certification;
use App\Models\CertificationCoachAssignment;
use App\Models\Enrollment;
use App\Models\EnrollmentNote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class EnrollmentNoteTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 担当コーチにはコーチメモが表示される。
     */
    public function test_assigned_coach_can_see_enrollment_notes(): void
    {
        $coach = User::factory()->coach()->create();
        $admin = User::factory()->admin()->create();
        $student = User::factory()->student()->create();
        $certification = Certification::factory()->published()->create();
        $enrollment = Enrollment::factory()
            ->for($student)
            ->for($certification)
            ->learning()
            ->create();

        CertificationCoachAssignment::create([
            'id' => (string) Str::ulid(),
            'certification_id' => $certification->id,
            'user_id' => $coach->id,
            'assigned_by_user_id' => $admin->id,
            'assigned_at' => now(),
        ]);

        EnrollmentNote::factory()
            ->for($enrollment)
            ->for($coach, 'author')
            ->create([
                'body' => 'コーチメモの表示確認',
            ]);

        $response = $this->actingAs($coach)
            ->get(route('enrollments.show', $enrollment));

        $response->assertOk();
        $response->assertSee('コーチメモ');
        $response->assertSee('コーチメモの表示確認');
        $response->assertSee($coach->name);
    }

    /**
     * 管理者にはコーチメモが表示される。
     */
    public function test_admin_can_see_enrollment_notes(): void
    {
        $admin = User::factory()->admin()->create();
        $student = User::factory()->student()->create();
        $certification = Certification::factory()->published()->create();
        $enrollment = Enrollment::factory()
            ->for($student)
            ->for($certification)
            ->learning()
            ->create();

        EnrollmentNote::factory()
            ->for($enrollment)
            ->for($admin, 'author')
            ->create([
                'body' => '管理者メモの表示確認',
            ]);

        $response = $this->actingAs($admin)
            ->get(route('enrollments.show', $enrollment));

        $response->assertOk();
        $response->assertSee('コーチメモ');
        $response->assertSee('管理者メモの表示確認');
    }

    /**
     * 受講生にはコーチメモ自体が表示されない。
     */
    public function test_student_cannot_see_enrollment_notes(): void
    {
        $student = User::factory()->student()->create();
        $certification = Certification::factory()->published()->create();
        $enrollment = Enrollment::factory()
            ->for($student)
            ->for($certification)
            ->learning()
            ->create();

        EnrollmentNote::factory()
            ->for($enrollment)
            ->create([
                'body' => '受講生には見えないメモ',
            ]);

        $response = $this->actingAs($student)
            ->get(route('enrollments.show', $enrollment));

        $response->assertOk();
        $response->assertDontSee('コーチメモ');
        $response->assertDontSee('受講生には見えないメモ');
    }

    /**
     * 担当コーチは自分のメモを編集・削除でき、他コーチのメモは閲覧のみできる。
     */
    public function test_assigned_coach_can_operate_own_note_but_not_other_coachs_note(): void
    {
        $coach = User::factory()->coach()->create();
        $otherCoach = User::factory()->coach()->create();
        $admin = User::factory()->admin()->create();
        $student = User::factory()->student()->create();
        $certification = Certification::factory()->published()->create();
        $enrollment = Enrollment::factory()
            ->for($student)
            ->for($certification)
            ->learning()
            ->create();

        CertificationCoachAssignment::create([
            'id' => (string) Str::ulid(),
            'certification_id' => $certification->id,
            'user_id' => $coach->id,
            'assigned_by_user_id' => $admin->id,
            'assigned_at' => now(),
        ]);

        CertificationCoachAssignment::create([
            'id' => (string) Str::ulid(),
            'certification_id' => $certification->id,
            'user_id' => $otherCoach->id,
            'assigned_by_user_id' => $admin->id,
            'assigned_at' => now(),
        ]);

        $ownNote = EnrollmentNote::factory()
            ->for($enrollment)
            ->for($coach, 'author')
            ->create([
                'body' => '自分のメモ',
            ]);

        $otherNote = EnrollmentNote::factory()
            ->for($enrollment)
            ->for($otherCoach, 'author')
            ->create([
                'body' => '他コーチのメモ',
            ]);

        $response = $this->actingAs($coach)
            ->get(route('enrollments.show', $enrollment));

        $response->assertOk();
        $response->assertSee('自分のメモ');
        $response->assertSee('他コーチのメモ');

        $response->assertSee(route('enrollment-notes.edit', $ownNote));
        $response->assertSee(route('enrollment-notes.destroy', $ownNote));

        $response->assertDontSee(route('enrollment-notes.edit', $otherNote));
        $response->assertDontSee(route('enrollment-notes.destroy', $otherNote));
    }
}
