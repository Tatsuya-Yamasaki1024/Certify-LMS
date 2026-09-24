<?php

declare(strict_types=1);

namespace Tests\Unit\Notifications\Announcement;

use App\Models\Announcement;
use App\Models\User;
use App\Notifications\Announcement\AdminAnnouncementNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Messages\MailMessage;
use Tests\TestCase;

/**
 * AdminAnnouncementNotification の配信内容を検証する Unit テスト。
 *
 * 運営からのお知らせが database と mail の両方で配信され、
 * 通知データとメールの内容が Announcement の情報から正しく生成されることを検証する。
 */
class AdminAnnouncementNotificationTest extends TestCase
{
    use RefreshDatabase;

    // database と mail の両方の通知チャンネルを使用することを確認する。
    public function test_via_uses_database_and_mail_channels(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();

        $announcement = Announcement::create([
            'title' => '運営からのお知らせ',
            'body' => 'お知らせ本文です。',
            'target_type' => 'all_students',
            'created_by_user_id' => $admin->id,
        ]);

        $user = User::factory()->student()->inProgress()->create();
        $notification = new AdminAnnouncementNotification($announcement);

        // Act
        $channels = $notification->via($user);

        // Assert
        $this->assertSame(['database', 'mail'], $channels);
    }

    // データベース通知にお知らせの種別・タイトル・本文・お知らせ ID が正しく格納されることを確認する。
    public function test_to_database_contains_announcement_data(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();

        $announcement = Announcement::create([
            'title' => 'メンテナンスのお知らせ',
            'body' => "9月30日にメンテナンスを実施します。\nご確認ください。",
            'target_type' => 'all_students',
            'created_by_user_id' => $admin->id,
        ]);

        $user = User::factory()->student()->inProgress()->create();
        $notification = new AdminAnnouncementNotification($announcement);

        // Act
        $data = $notification->toDatabase($user);

        // Assert
        $this->assertSame([
            'notification_type' => 'admin_announcement',
            'title' => 'メンテナンスのお知らせ',
            'message' => "9月30日にメンテナンスを実施します。\nご確認ください。",
            'body' => "9月30日にメンテナンスを実施します。\nご確認ください。",
            'announcement_id' => $announcement->id,
        ], $data);
    }

    // メールの件名に Certify LMS 運営からのお知らせとタイトルが設定されることを確認する。
    public function test_to_mail_has_announcement_subject(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();

        $announcement = Announcement::create([
            'title' => '重要なお知らせ',
            'body' => '受講に関する重要なお知らせです。',
            'target_type' => 'all_students',
            'created_by_user_id' => $admin->id,
        ]);

        $user = User::factory()->student()->inProgress()->create();
        $notification = new AdminAnnouncementNotification($announcement);

        // Act
        $mail = $notification->toMail($user);

        // Assert
        $this->assertInstanceOf(MailMessage::class, $mail);
        $this->assertSame(
            'Certify LMS 運営からのお知らせ：重要なお知らせ',
            $mail->subject,
        );
    }

    // メールに受講生への宛名・お知らせ本文・署名が正しく設定されることを確認する。
    public function test_to_mail_contains_greeting_and_body(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();

        $announcement = Announcement::create([
            'title' => 'サービス変更のお知らせ',
            'body' => '10月1日からサービス内容が変更されます。',
            'target_type' => 'all_students',
            'created_by_user_id' => $admin->id,
        ]);

        $user = User::factory()
            ->student()
            ->inProgress()
            ->create([
                'name' => '山田太郎',
            ]);

        $notification = new AdminAnnouncementNotification($announcement);

        // Act
        $mail = $notification->toMail($user);

        // Assert
        $this->assertInstanceOf(MailMessage::class, $mail);
        $this->assertSame('山田太郎 様', $mail->greeting);
        $this->assertContains(
            '10月1日からサービス内容が変更されます。',
            $mail->introLines,
        );
        $this->assertSame('Certify LMS 運営チーム', $mail->salutation);
    }
}
