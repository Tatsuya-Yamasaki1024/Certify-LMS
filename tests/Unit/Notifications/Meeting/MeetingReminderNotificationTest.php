<?php

declare(strict_types=1);

namespace Tests\Unit\Notifications\Meeting;

use App\Enums\MeetingReminderType;
use App\Models\Meeting;
use App\Models\User;
use App\Notifications\Meeting\MeetingReminderNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Messages\MailMessage;
use Tests\TestCase;

/**
 * MeetingReminderNotification の配信内容を検証する Unit テスト。
 *
 * 面談リマインダーが database と mail の両方で配信され、
 * リマインダー種別に応じた通知データとメール内容が正しく生成されることを検証する。
 */
class MeetingReminderNotificationTest extends TestCase
{
    use RefreshDatabase;

    // database と mail の両方の通知チャンネルを使用することを確認する。
    public function test_via_uses_database_and_mail_channels(): void
    {
        // Arrange
        $user = User::factory()->student()->inProgress()->create();
        $meeting = Meeting::factory()
            ->reserved()
            ->forStudent($user)
            ->create();

        $notification = new MeetingReminderNotification(
            $meeting,
            MeetingReminderType::Eve,
        );

        // Act
        $channels = $notification->via($user);

        // Assert
        $this->assertSame(['database', 'mail'], $channels);
    }

    // データベース通知に前日リマインダーの種別・タイトル・本文・面談 ID が正しく格納されることを確認する。
    public function test_to_database_contains_eve_reminder_data(): void
    {
        // Arrange
        $user = User::factory()->student()->inProgress()->create();
        $meeting = Meeting::factory()
            ->reserved()
            ->forStudent($user)
            ->create();

        $notification = new MeetingReminderNotification(
            $meeting,
            MeetingReminderType::Eve,
        );

        // Act
        $data = $notification->toDatabase($user);

        // Assert
        $this->assertSame([
            'notification_type' => 'meeting_reminder',
            'title' => '明日の面談のお知らせ',
            'message' => '明日、面談の予定があります。',
            'meeting_id' => $meeting->id,
            'reminder_type' => 'eve',
        ], $data);
    }

    // 前日リマインダーのメール件名・宛名・本文・署名が正しく設定されることを確認する。
    public function test_to_mail_contains_eve_reminder_content(): void
    {
        // Arrange
        $user = User::factory()
            ->student()
            ->inProgress()
            ->create([
                'name' => '山田太郎',
            ]);

        $meeting = Meeting::factory()
            ->reserved()
            ->forStudent($user)
            ->create([
                'scheduled_at' => now()->addDay()->setTime(10, 0),
            ]);

        $notification = new MeetingReminderNotification(
            $meeting,
            MeetingReminderType::Eve,
        );

        // Act
        $mail = $notification->toMail($user);

        // Assert
        $this->assertInstanceOf(MailMessage::class, $mail);
        $this->assertSame(
            'Certify LMS 明日の面談のお知らせ',
            $mail->subject,
        );
        $this->assertSame('山田太郎 様', $mail->greeting);
        $this->assertContains(
            '明日、面談の予定があります。',
            $mail->introLines,
        );
        $this->assertContains(
            '予約日時：'.$meeting->scheduled_at->format('Y年m月d日 H:i'),
            $mail->introLines,
        );
        $this->assertSame('Certify LMS 運営チーム', $mail->salutation);
    }

    // 1時間前リマインダーのメール件名・宛名・本文・署名が正しく設定されることを確認する。
    public function test_to_mail_contains_one_hour_before_reminder_content(): void
    {
        // Arrange
        $user = User::factory()
            ->student()
            ->inProgress()
            ->create([
                'name' => '山田太郎',
            ]);

        $meeting = Meeting::factory()
            ->reserved()
            ->forStudent($user)
            ->create([
                'scheduled_at' => now()->addHour()->setTime(
                    now()->addHour()->hour,
                    0,
                ),
            ]);

        $notification = new MeetingReminderNotification(
            $meeting,
            MeetingReminderType::OneHourBefore,
        );

        // Act
        $mail = $notification->toMail($user);

        // Assert
        $this->assertInstanceOf(MailMessage::class, $mail);
        $this->assertSame(
            'Certify LMS まもなく面談の時間です',
            $mail->subject,
        );
        $this->assertSame('山田太郎 様', $mail->greeting);
        $this->assertContains(
            '1時間後に面談の予定があります。',
            $mail->introLines,
        );
        $this->assertContains(
            '予約日時：'.$meeting->scheduled_at->format('Y年m月d日 H:i'),
            $mail->introLines,
        );
        $this->assertSame('Certify LMS 運営チーム', $mail->salutation);
    }
}
