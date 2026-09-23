<?php

declare(strict_types=1);

namespace Tests\Feature\Http\EnrollmentNote;

use App\Models\Certification;
use App\Models\CertificationCoachAssignment;
use App\Models\Enrollment;
use App\Models\EnrollmentNote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class EnrollmentNoteControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 担当資格のコーチは受講登録にメモを追加できる。
     */
    public function test_assigned_coach_can_store_note(): void
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

        $response = $this->actingAs($coach)->post(
            route('enrollments.notes.store', $enrollment),
            ['body' => '次回は模擬試験の進捗を確認する。']
        );

        $response->assertRedirect(route('enrollments.show', $enrollment));

        $this->assertDatabaseHas('enrollment_notes', [
            'enrollment_id' => $enrollment->id,
            'user_id' => $coach->id,
            'body' => '次回は模擬試験の進捗を確認する。',
        ]);
    }

    /**
     * 担当外資格のコーチはメモを追加できない。
     */
    public function test_unassigned_coach_cannot_store_note(): void
    {
        $coach = User::factory()->coach()->create();
        $certification = Certification::factory()->published()->create();

        $enrollment = Enrollment::factory()
            ->for($certification)
            ->learning()
            ->create();

        $response = $this->actingAs($coach)->postJson(
            route('enrollments.notes.store', $enrollment),
            ['body' => 'メモ']
        );

        $response->assertForbidden();

        $this->assertDatabaseCount('enrollment_notes', 0);
    }

    /**
     * Adminは任意の受講登録にメモを追加できる。
     */
    public function test_admin_can_store_note(): void
    {
        $admin = User::factory()->admin()->create();
        $certification = Certification::factory()->published()->create();
        $enrollment = Enrollment::factory()
            ->for($certification)
            ->learning()
            ->create();

        $response = $this->actingAs($admin)->post(
            route('enrollments.notes.store', $enrollment),
            ['body' => 'Adminからのメモ']
        );

        $response->assertRedirect(route('enrollments.show', $enrollment));

        $this->assertDatabaseHas('enrollment_notes', [
            'enrollment_id' => $enrollment->id,
            'user_id' => $admin->id,
            'body' => 'Adminからのメモ',
        ]);
    }

    /**
     * Studentはメモを追加できない。
     */
    public function test_student_cannot_store_note(): void
    {
        $student = User::factory()->student()->create();
        $enrollment = Enrollment::factory()
            ->for($student)
            ->learning()
            ->create();

        $response = $this->actingAs($student)->postJson(
            route('enrollments.notes.store', $enrollment),
            ['body' => 'Studentからのメモ']
        );

        $response->assertForbidden();

        $this->assertDatabaseCount('enrollment_notes', 0);
    }

    /**
     * 担当資格のコーチは自分のメモの編集画面を表示できる。
     */
    public function test_assigned_coach_can_view_edit_note(): void
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
            ->create([
                'body' => '編集画面で表示するメモ',
            ]);

        $response = $this->actingAs($coach)
            ->get(route('enrollment-notes.edit', $note));

        $response->assertOk();
        $response->assertSee('編集画面で表示するメモ');
    }

    /**
     * 担当から外れたコーチは過去に作成したメモを操作できない。
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
            ->create([
                'body' => '担当解除後も残っているメモ',
            ]);

        $assignment->update([
            'unassigned_at' => now(),
        ]);

        $editResponse = $this->actingAs($coach)
            ->getJson(route('enrollment-notes.edit', $note));

        $editResponse->assertForbidden();

        $updateResponse = $this->actingAs($coach)
            ->patchJson(
                route('enrollment-notes.update', $note),
                ['body' => '担当解除後の不正な更新']
            );

        $updateResponse->assertForbidden();

        $deleteResponse = $this->actingAs($coach)
            ->deleteJson(route('enrollment-notes.destroy', $note));

        $deleteResponse->assertForbidden();

        $this->assertDatabaseHas('enrollment_notes', [
            'id' => $note->id,
            'body' => '担当解除後も残っているメモ',
        ]);
    }

    /**
     * 担当資格のコーチは自分のメモを更新できる。
     */
    public function test_assigned_coach_can_update_own_note(): void
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

        $response = $this->actingAs($coach)->patch(
            route('enrollment-notes.update', $note),
            ['body' => '更新後のメモ']
        );

        $response->assertRedirect(route('enrollments.show', $enrollment));

        $this->assertDatabaseHas('enrollment_notes', [
            'id' => $note->id,
            'body' => '更新後のメモ',
        ]);
    }

    /**
     * 他コーチのメモは更新できない。
     */
    public function test_coach_cannot_update_other_coachs_note(): void
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

        $response = $this->actingAs($coach)->patchJson(
            route('enrollment-notes.update', $note),
            ['body' => '不正な更新']
        );

        $response->assertForbidden();

        $this->assertDatabaseHas('enrollment_notes', [
            'id' => $note->id,
            'body' => $note->body,
        ]);
    }

    /**
     * Adminは他コーチのメモを更新できる。
     */
    public function test_admin_can_update_note(): void
    {
        $admin = User::factory()->admin()->create();
        $coach = User::factory()->coach()->create();
        $enrollment = Enrollment::factory()->learning()->create();
        $note = EnrollmentNote::factory()
            ->for($enrollment)
            ->for($coach, 'author')
            ->create();

        $response = $this->actingAs($admin)->patch(
            route('enrollment-notes.update', $note),
            ['body' => 'Adminによる更新']
        );

        $response->assertRedirect(route('enrollments.show', $enrollment));

        $this->assertDatabaseHas('enrollment_notes', [
            'id' => $note->id,
            'body' => 'Adminによる更新',
        ]);
    }

    /**
     * Studentはメモを更新できない。
     */
    public function test_student_cannot_update_note(): void
    {
        $student = User::factory()->student()->create();
        $coach = User::factory()->coach()->create();
        $enrollment = Enrollment::factory()
            ->for($student)
            ->learning()
            ->create();
        $note = EnrollmentNote::factory()
            ->for($enrollment)
            ->for($coach, 'author')
            ->create();

        $response = $this->actingAs($student)->patchJson(
            route('enrollment-notes.update', $note),
            ['body' => '不正な更新']
        );

        $response->assertForbidden();

        $this->assertDatabaseHas('enrollment_notes', [
            'id' => $note->id,
            'body' => $note->body,
        ]);
    }

    /**
     * 担当資格のコーチは自分のメモを削除できる。
     */
    public function test_assigned_coach_can_delete_own_note(): void
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

        $response = $this->actingAs($coach)->delete(
            route('enrollment-notes.destroy', $note)
        );

        $response->assertRedirect(route('enrollments.show', $enrollment));

        $this->assertDatabaseMissing('enrollment_notes', [
            'id' => $note->id,
        ]);
    }

    /**
     * 他コーチのメモは削除できない。
     */
    public function test_coach_cannot_delete_other_coachs_note(): void
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

        $response = $this->actingAs($coach)->deleteJson(
            route('enrollment-notes.destroy', $note)
        );

        $response->assertForbidden();

        $this->assertDatabaseHas('enrollment_notes', [
            'id' => $note->id,
        ]);
    }

    /**
     * Adminは他コーチのメモを削除できる。
     */
    public function test_admin_can_delete_note(): void
    {
        $admin = User::factory()->admin()->create();
        $coach = User::factory()->coach()->create();
        $enrollment = Enrollment::factory()->learning()->create();
        $note = EnrollmentNote::factory()
            ->for($enrollment)
            ->for($coach, 'author')
            ->create();

        $response = $this->actingAs($admin)->delete(
            route('enrollment-notes.destroy', $note)
        );

        $response->assertRedirect(route('enrollments.show', $enrollment));

        $this->assertDatabaseMissing('enrollment_notes', [
            'id' => $note->id,
        ]);
    }

    /**
     * Studentはメモを削除できない。
     */
    public function test_student_cannot_delete_note(): void
    {
        $student = User::factory()->student()->create();
        $coach = User::factory()->coach()->create();
        $enrollment = Enrollment::factory()
            ->for($student)
            ->learning()
            ->create();
        $note = EnrollmentNote::factory()
            ->for($enrollment)
            ->for($coach, 'author')
            ->create();

        $response = $this->actingAs($student)->deleteJson(
            route('enrollment-notes.destroy', $note)
        );

        $response->assertForbidden();

        $this->assertDatabaseHas('enrollment_notes', [
            'id' => $note->id,
        ]);
    }

    /**
     * メモ本文が未入力の場合は追加できない。
     */
    public function test_store_rejects_empty_body(): void
    {
        $admin = User::factory()->admin()->create();
        $enrollment = Enrollment::factory()->learning()->create();

        $response = $this->actingAs($admin)->post(
            route('enrollments.notes.store', $enrollment),
            ['body' => '']
        );

        $response->assertSessionHasErrors('body');

        $this->assertDatabaseCount('enrollment_notes', 0);
    }

    /**
     * メモ本文が2000文字を超える場合は追加できない。
     */
    public function test_store_rejects_body_over_2000_characters(): void
    {
        $admin = User::factory()->admin()->create();
        $enrollment = Enrollment::factory()->learning()->create();

        $response = $this->actingAs($admin)->post(
            route('enrollments.notes.store', $enrollment),
            ['body' => str_repeat('あ', 2001)]
        );

        $response->assertSessionHasErrors('body');

        $this->assertDatabaseCount('enrollment_notes', 0);
    }

    /**
     * メモ本文が2000文字の場合は追加できる。
     */
    public function test_store_accepts_body_of_2000_characters(): void
    {
        $admin = User::factory()->admin()->create();
        $enrollment = Enrollment::factory()->learning()->create();
        $body = str_repeat('あ', 2000);

        $response = $this->actingAs($admin)->post(
            route('enrollments.notes.store', $enrollment),
            ['body' => $body]
        );

        $response->assertRedirect(route('enrollments.show', $enrollment));

        $this->assertDatabaseHas('enrollment_notes', [
            'enrollment_id' => $enrollment->id,
            'user_id' => $admin->id,
            'body' => $body,
        ]);
    }

    /**
     * メモ本文が未入力の場合は更新できない。
     */
    public function test_update_rejects_empty_body(): void
    {
        $admin = User::factory()->admin()->create();
        $enrollment = Enrollment::factory()->learning()->create();
        $note = EnrollmentNote::factory()
            ->for($enrollment)
            ->for($admin, 'author')
            ->create();

        $response = $this->actingAs($admin)->patch(
            route('enrollment-notes.update', $note),
            ['body' => '']
        );

        $response->assertSessionHasErrors('body');

        $this->assertDatabaseHas('enrollment_notes', [
            'id' => $note->id,
            'body' => $note->body,
        ]);
    }

    /**
     * メモ本文が2000文字を超える場合は更新できない。
     */
    public function test_update_rejects_body_over_2000_characters(): void
    {
        $admin = User::factory()->admin()->create();
        $enrollment = Enrollment::factory()->learning()->create();
        $note = EnrollmentNote::factory()
            ->for($enrollment)
            ->for($admin, 'author')
            ->create();
        $body = str_repeat('あ', 2001);

        $response = $this->actingAs($admin)->patch(
            route('enrollment-notes.update', $note),
            ['body' => $body]
        );

        $response->assertSessionHasErrors('body');

        $this->assertDatabaseHas('enrollment_notes', [
            'id' => $note->id,
            'body' => $note->body,
        ]);
    }

    /**
     * メモ本文が2000文字の場合は更新できる。
     */
    public function test_update_accepts_body_of_2000_characters(): void
    {
        $admin = User::factory()->admin()->create();
        $enrollment = Enrollment::factory()->learning()->create();
        $note = EnrollmentNote::factory()
            ->for($enrollment)
            ->for($admin, 'author')
            ->create();
        $body = str_repeat('あ', 2000);

        $response = $this->actingAs($admin)->patch(
            route('enrollment-notes.update', $note),
            ['body' => $body]
        );

        $response->assertRedirect(route('enrollments.show', $enrollment));

        $this->assertDatabaseHas('enrollment_notes', [
            'id' => $note->id,
            'body' => $body,
        ]);
    }
}
