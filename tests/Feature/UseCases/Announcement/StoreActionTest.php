<?php

declare(strict_types=1);

namespace Tests\Feature\UseCases\Announcement;

use App\Enums\AnnouncementTargetType;
use App\Models\Certification;
use App\Models\Enrollment;
use App\Models\User;
use App\Notifications\Announcement\AdminAnnouncementNotification;
use App\UseCases\Announcement\StoreAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class StoreActionTest extends TestCase
{
    use RefreshDatabase;

    // 全受講生を配信対象にした場合、受講中の全受講生にお知らせが配信されることを確認する。
    public function test_all_students_receive_announcement(): void
    {
        Notification::fake();

        $admin = User::factory()->admin()->create();
        $students = User::factory()
            ->count(3)
            ->student()
            ->inProgress()
            ->create();

        $announcement = app(StoreAction::class)($admin, [
            'title' => '運営からのお知らせ',
            'body' => 'お知らせ本文です。',
            'target_type' => AnnouncementTargetType::AllStudents->value,
        ]);

        $this->assertDatabaseHas('announcements', [
            'id' => $announcement->id,
            'title' => '運営からのお知らせ',
            'body' => 'お知らせ本文です。',
            'target_type' => AnnouncementTargetType::AllStudents->value,
            'target_certification_id' => null,
            'target_user_id' => null,
            'created_by_user_id' => $admin->id,
            'dispatched_count' => 3,
        ]);

        Notification::assertSentTo(
            $students,
            AdminAnnouncementNotification::class,
        );
    }

    // 資格指定を配信対象にした場合、指定資格を受講している受講中の受講生にのみお知らせが配信されることを確認する。
    public function test_certification_students_receive_announcement(): void
    {
        Notification::fake();

        $admin = User::factory()->admin()->create();
        $certification = Certification::factory()->published()->create();

        $targetStudents = User::factory()
            ->count(2)
            ->student()
            ->inProgress()
            ->create();

        foreach ($targetStudents as $student) {
            Enrollment::factory()
                ->for($student)
                ->for($certification)
                ->create();
        }

        $otherStudent = User::factory()
            ->student()
            ->inProgress()
            ->create();

        $announcement = app(StoreAction::class)($admin, [
            'title' => '資格指定のお知らせ',
            'body' => '資格指定本文です。',
            'target_type' => AnnouncementTargetType::Certification->value,
            'target_certification_id' => $certification->id,
        ]);

        $this->assertDatabaseHas('announcements', [
            'id' => $announcement->id,
            'target_type' => AnnouncementTargetType::Certification->value,
            'target_certification_id' => $certification->id,
            'target_user_id' => null,
            'dispatched_count' => 2,
        ]);

        Notification::assertSentTo(
            $targetStudents,
            AdminAnnouncementNotification::class,
        );

        Notification::assertNotSentTo(
            $otherStudent,
            AdminAnnouncementNotification::class,
        );
    }

    // ユーザー指定で受講中の受講生を指定した場合、指定した受講生にのみお知らせが配信されることを確認する。
    public function test_user_receives_announcement_when_user_is_in_progress_student(): void
    {
        Notification::fake();

        $admin = User::factory()->admin()->create();

        $student = User::factory()
            ->student()
            ->inProgress()
            ->create();

        $otherStudent = User::factory()
            ->student()
            ->inProgress()
            ->create();

        $announcement = app(StoreAction::class)($admin, [
            'title' => 'ユーザー指定のお知らせ',
            'body' => 'ユーザー指定本文です。',
            'target_type' => AnnouncementTargetType::User->value,
            'target_user_id' => $student->id,
        ]);

        $this->assertDatabaseHas('announcements', [
            'id' => $announcement->id,
            'target_type' => AnnouncementTargetType::User->value,
            'target_certification_id' => null,
            'target_user_id' => $student->id,
            'dispatched_count' => 1,
        ]);

        Notification::assertSentTo(
            $student,
            AdminAnnouncementNotification::class,
        );

        Notification::assertNotSentTo(
            $otherStudent,
            AdminAnnouncementNotification::class,
        );
    }


    // 全受講生を配信対象にした場合、招待中・卒業・退会済の受講生にはお知らせが配信されないことを確認する。
    public function test_only_in_progress_students_receive_announcement(): void
    {
        Notification::fake();

        $admin = User::factory()->admin()->create();

        $inProgressStudent = User::factory()
            ->student()
            ->inProgress()
            ->create();

        $invitedStudent = User::factory()
            ->student()
            ->invited()
            ->create();

        $graduatedStudent = User::factory()
            ->student()
            ->graduated()
            ->create();

        $withdrawnStudent = User::factory()
            ->student()
            ->withdrawn()
            ->create();

        $announcement = app(StoreAction::class)($admin, [
            'title' => '受講中のみのお知らせ',
            'body' => '受講中の学生だけに配信します。',
            'target_type' => AnnouncementTargetType::AllStudents->value,
        ]);

        $this->assertSame(1, $announcement->fresh()->dispatched_count);

        Notification::assertSentTo(
            $inProgressStudent,
            AdminAnnouncementNotification::class,
        );

        Notification::assertNotSentTo(
            $invitedStudent,
            AdminAnnouncementNotification::class,
        );

        Notification::assertNotSentTo(
            $graduatedStudent,
            AdminAnnouncementNotification::class,
        );

        Notification::assertNotSentTo(
            $withdrawnStudent,
            AdminAnnouncementNotification::class,
        );
    }

    // ユーザー指定で受講中ではない受講生を指定した場合、お知らせが配信されないことを確認する。
    public function test_user_does_not_receive_announcement_when_user_is_not_in_progress_student(): void
    {
        Notification::fake();

        $admin = User::factory()->admin()->create();
        $graduatedStudent = User::factory()
            ->student()
            ->graduated()
            ->create();

        $announcement = app(StoreAction::class)($admin, [
            'title' => 'ユーザー指定のお知らせ',
            'body' => '対象外ユーザーには配信されません。',
            'target_type' => AnnouncementTargetType::User->value,
            'target_user_id' => $graduatedStudent->id,
        ]);

        $this->assertSame(0, $announcement->fresh()->dispatched_count);

        Notification::assertNotSentTo(
            $graduatedStudent,
            AdminAnnouncementNotification::class,
        );
    }
}
