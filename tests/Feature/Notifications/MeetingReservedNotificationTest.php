<?php

declare(strict_types=1);

namespace Tests\Feature\Notifications;

use App\Enums\MeetingStatus;
use App\Models\Certification;
use App\Models\CoachAvailability;
use App\Models\Enrollment;
use App\Models\Meeting;
use App\Models\User;
use App\Notifications\Meeting\MeetingReservedNotification;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

class MeetingReservedNotificationTest extends TestCase
{
    use DatabaseMigrations;

    private function attachCoach(
        Certification $certification,
        User $coach,
        User $admin,
    ): void {
        $certification->coaches()->attach($coach->id, [
            'id' => (string) Str::ulid(),
            'assigned_by_user_id' => $admin->id,
            'assigned_at' => now(),
            'unassigned_at' => null,
        ]);
    }

    // 面談を予約した場合、担当コーチに通知されることを確認する。
    public function test_meeting_reservation_notifies_assigned_coach(): void
    {
        // Arrange
        Notification::fake();

        $student = User::factory()
            ->student()
            ->inProgress()
            ->create([
                'max_meetings' => 3,
            ]);

        $admin = User::factory()
            ->admin()
            ->create();

        $coach = User::factory()
            ->coach()
            ->inProgress()
            ->create([
                'meeting_url' => 'https://meet.example.com/coach-room',
            ]);

        $certification = Certification::factory()
            ->published()
            ->create();

        $this->attachCoach($certification, $coach, $admin);

        CoachAvailability::factory()
            ->forCoach($coach)
            ->onDay(1)
            ->timeRange('09:00:00', '18:00:00')
            ->create();

        $enrollment = Enrollment::factory()
            ->for($student, 'user')
            ->for($certification)
            ->learning()
            ->create();

        $scheduledAt = now()
            ->startOfDay()
            ->next(Carbon::MONDAY)
            ->setTime(10, 0);

        // Act
        $response = $this->actingAs($student)->post(
            route('meetings.store', $enrollment),
            [
                'scheduled_at' => $scheduledAt->format('Y-m-d\TH:i:s'),
                'topic' => '相談したい',
            ],
        );

        // Assert
        $response->assertRedirect();

        $meetingId = Meeting::query()
            ->latest('created_at')
            ->value('id');

        $this->assertDatabaseHas('meetings', [
            'id' => $meetingId,
            'student_id' => $student->id,
            'coach_id' => $coach->id,
            'status' => MeetingStatus::Reserved->value,
        ]);

        Notification::assertSentTo(
            $coach,
            MeetingReservedNotification::class,
        );

        Notification::assertNotSentTo(
            $student,
            MeetingReservedNotification::class,
        );
    }

    // 面談予約通知メールの件名、本文、予約日時、確認リンクが正しいことを確認する。
    public function test_meeting_reserved_notification_mail_content_is_correct(): void
    {
        // Arrange
        Notification::fake();

        $student = User::factory()
            ->student()
            ->inProgress()
            ->create([
                'max_meetings' => 3,
            ]);

        $admin = User::factory()
            ->admin()
            ->create();

        $coach = User::factory()
            ->coach()
            ->inProgress()
            ->create([
                'meeting_url' => 'https://meet.example.com/coach-room',
            ]);

        $certification = Certification::factory()
            ->published()
            ->create();

        $this->attachCoach($certification, $coach, $admin);

        CoachAvailability::factory()
            ->forCoach($coach)
            ->onDay(1)
            ->timeRange('09:00:00', '18:00:00')
            ->create();

        $enrollment = Enrollment::factory()
            ->for($student, 'user')
            ->for($certification)
            ->learning()
            ->create();

        $scheduledAt = now()
            ->startOfDay()
            ->next(Carbon::MONDAY)
            ->setTime(10, 0);

        // Act
        $response = $this->actingAs($student)->post(
            route('meetings.store', $enrollment),
            [
                'scheduled_at' => $scheduledAt->format('Y-m-d\TH:i:s'),
                'topic' => 'メール通知テスト',
            ],
        );

        // Assert
        $response->assertRedirect();

        $meeting = Meeting::query()
            ->latest('created_at')
            ->firstOrFail();

        Notification::assertSentTo(
            $coach,
            MeetingReservedNotification::class,
            function (MeetingReservedNotification $notification) use (
                $coach,
                $meeting,
            ): bool {
                $mail = $notification->toMail($coach);

                $this->assertSame(
                    'Certify LMS 面談予約のお知らせ',
                    $mail->subject,
                );

                $this->assertSame(
                    $coach->name.' 様',
                    $mail->greeting,
                );

                $this->assertContains(
                    '受講生から面談の予約が入りました。',
                    $mail->introLines,
                );

                $this->assertContains(
                    '予約日時：'.$meeting->scheduled_at->format('Y年m月d日 H:i'),
                    $mail->introLines,
                );

                $this->assertSame(
                    '面談を確認する',
                    $mail->actionText,
                );

                $this->assertSame(
                    route('meetings.show', $meeting),
                    $mail->actionUrl,
                );

                return true;
            },
        );
    }
}
