<?php

declare(strict_types=1);

namespace Tests\Feature\Commands;

use App\Enums\UserStatus;
use App\Models\Meeting;
use App\Models\User;
use App\Notifications\Meeting\MeetingReminderNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SendMeetingRemindersCommandTest extends TestCase
{
    use RefreshDatabase;

    // 前日リマインダーが学生とコーチの両方に送信されることを確認する。
    public function test_sends_eve_reminder_to_student_and_coach(): void
    {
        Notification::fake();

        $coach = User::factory()->coach()->create([
            'status' => UserStatus::InProgress,
        ]);

        $student = User::factory()->student()->create([
            'status' => UserStatus::InProgress,
        ]);

        Meeting::factory()
            ->reserved()
            ->forCoach($coach)
            ->forStudent($student)
            ->create([
                'scheduled_at' => now()->addDay()->setTime(10, 0),
            ]);

        $this->artisan('notifications:send-meeting-reminders', [
            '--window' => 'eve',
        ])->assertSuccessful();

        Notification::assertSentTo(
            $student,
            MeetingReminderNotification::class,
        );

        Notification::assertSentTo(
            $coach,
            MeetingReminderNotification::class,
        );
    }

    // 1時間前リマインダーが学生とコーチの両方に送信されることを確認する。
    public function test_sends_one_hour_before_reminder_to_student_and_coach(): void
    {
        Notification::fake();

        $coach = User::factory()->coach()->create([
            'status' => UserStatus::InProgress,
        ]);

        $student = User::factory()->student()->create([
            'status' => UserStatus::InProgress,
        ]);

        Meeting::factory()
            ->reserved()
            ->forCoach($coach)
            ->forStudent($student)
            ->create([
                'scheduled_at' => now()->addMinutes(60),
            ]);

        $this->artisan('notifications:send-meeting-reminders', [
            '--window' => 'one_hour_before',
        ])->assertSuccessful();

        Notification::assertSentTo(
            $student,
            MeetingReminderNotification::class,
        );

        Notification::assertSentTo(
            $coach,
            MeetingReminderNotification::class,
        );
    }

    // Canceled の面談にはリマインダーが送信されないことを確認する。
    public function test_does_not_send_reminder_for_canceled_meeting(): void
    {
        Notification::fake();

        $coach = User::factory()->coach()->create([
            'status' => UserStatus::InProgress,
        ]);

        $student = User::factory()->student()->create([
            'status' => UserStatus::InProgress,
        ]);

        Meeting::factory()
            ->canceled()
            ->forCoach($coach)
            ->forStudent($student)
            ->create([
                'scheduled_at' => now()->addDay()->setTime(10, 0),
            ]);

        $this->artisan('notifications:send-meeting-reminders', [
            '--window' => 'eve',
        ])->assertSuccessful();

        Notification::assertNothingSent();
    }

    // 5分間隔で3回実行されても同じリマインダーが二重送信されないことを確認する。
    public function test_does_not_send_duplicate_one_hour_before_reminder(): void
    {
        Notification::fake();

        $coach = User::factory()->coach()->create([
            'status' => UserStatus::InProgress,
        ]);

        $student = User::factory()->student()->create([
            'status' => UserStatus::InProgress,
        ]);

        $meeting = Meeting::factory()
            ->reserved()
            ->forCoach($coach)
            ->forStudent($student)
            ->create([
                'scheduled_at' => now()->addHour(),
            ]);

        $this->travelTo($meeting->scheduled_at->copy()->subMinutes(65));

        $this->artisan('notifications:send-meeting-reminders', [
            '--window' => 'one_hour_before',
        ])->assertSuccessful();

        $this->travelTo($meeting->scheduled_at->copy()->subMinutes(60));

        $this->artisan('notifications:send-meeting-reminders', [
            '--window' => 'one_hour_before',
        ])->assertSuccessful();

        $this->travelTo($meeting->scheduled_at->copy()->subMinutes(55));

        $this->artisan('notifications:send-meeting-reminders', [
            '--window' => 'one_hour_before',
        ])->assertSuccessful();

        Notification::assertSentToTimes(
            $student,
            MeetingReminderNotification::class,
            1,
        );

        Notification::assertSentToTimes(
            $coach,
            MeetingReminderNotification::class,
            1,
        );
    }

    // 1時間前リマインダーの対象時間外の面談には送信されないことを確認する。
    public function test_does_not_send_one_hour_before_reminder_for_meeting_outside_window(): void
    {
        Notification::fake();

        $coach = User::factory()->coach()->create([
            'status' => UserStatus::InProgress,
        ]);

        $student = User::factory()->student()->create([
            'status' => UserStatus::InProgress,
        ]);

        Meeting::factory()
            ->reserved()
            ->forCoach($coach)
            ->forStudent($student)
            ->create([
                'scheduled_at' => now()->addMinutes(10),
            ]);

        $this->artisan('notifications:send-meeting-reminders', [
            '--window' => 'one_hour_before',
        ])->assertSuccessful();

        Notification::assertNothingSent();
    }
}
