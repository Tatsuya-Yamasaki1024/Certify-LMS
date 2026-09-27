<?php

declare(strict_types=1);

namespace Tests\Feature\Notifications;

use App\Models\Certification;
use App\Models\ChatMember;
use App\Models\ChatRoom;
use App\Models\Enrollment;
use App\Models\User;
use App\Notifications\Chat\ChatMessageReceivedNotification;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

class ChatMessageReceivedNotificationTest extends TestCase
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

    // チャットメッセージ送信時、送信者以外のChatMemberに通知されることを確認する。
    public function test_chat_message_notifies_other_chat_member(): void
    {
        // Arrange
        Notification::fake();

        $student = User::factory()
            ->student()
            ->inProgress()
            ->create();

        $coach = User::factory()
            ->coach()
            ->inProgress()
            ->create();

        $admin = User::factory()
            ->admin()
            ->inProgress()
            ->create();

        $certification = Certification::factory()
            ->published()
            ->create();

        $this->attachCoach($certification, $coach, $admin);

        $enrollment = Enrollment::factory()
            ->for($student)
            ->for($certification)
            ->create();

        $room = ChatRoom::factory()
            ->for($enrollment)
            ->create();

        ChatMember::factory()->create([
            'chat_room_id' => $room->id,
            'user_id' => $student->id,
        ]);

        ChatMember::factory()->create([
            'chat_room_id' => $room->id,
            'user_id' => $coach->id,
        ]);

        // Act
        $response = $this->actingAs($student)->post(
            route('chat.storeMessage', $room),
            [
                'body' => '通知テストのメッセージです。',
            ],
        );

        // Assert
        $response->assertRedirect(route('chat.show', $room));

        Notification::assertSentTo(
            $coach,
            ChatMessageReceivedNotification::class,
        );

        Notification::assertNotSentTo(
            $student,
            ChatMessageReceivedNotification::class,
        );
    }

    // in_progress以外のChatMemberには通知されないことを確認する。
    public function test_chat_message_does_not_notify_inactive_chat_member(): void
    {
        // Arrange
        Notification::fake();

        $sender = User::factory()
            ->student()
            ->inProgress()
            ->create();

        $graduatedStudent = User::factory()
            ->student()
            ->graduated()
            ->create();

        $admin = User::factory()
            ->admin()
            ->create();

        $coach = User::factory()
            ->coach()
            ->inProgress()
            ->create();

        $certification = Certification::factory()
            ->published()
            ->create();

        $this->attachCoach($certification, $coach, $admin);

        $enrollment = Enrollment::factory()
            ->for($sender)
            ->for($certification)
            ->create();

        $room = ChatRoom::factory()
            ->for($enrollment)
            ->create();

        ChatMember::factory()->create([
            'chat_room_id' => $room->id,
            'user_id' => $sender->id,
        ]);

        ChatMember::factory()->create([
            'chat_room_id' => $room->id,
            'user_id' => $graduatedStudent->id,
        ]);

        // Act
        $response = $this->actingAs($sender)->post(
            route('chat.storeMessage', $room),
            [
                'body' => '非対象ユーザー通知テストです。',
            ],
        );

        // Assert
        $response->assertRedirect(route('chat.show', $room));

        Notification::assertNotSentTo(
            $graduatedStudent,
            ChatMessageReceivedNotification::class,
        );
    }

    // Chat通知メールの件名、本文、確認リンクが正しいことを確認する。
    public function test_chat_message_notification_mail_content_is_correct(): void
    {
        // Arrange
        Notification::fake();

        $student = User::factory()
            ->student()
            ->inProgress()
            ->create();

        $coach = User::factory()
            ->coach()
            ->inProgress()
            ->create();

        $admin = User::factory()
            ->admin()
            ->create();

        $certification = Certification::factory()
            ->published()
            ->create();

        $this->attachCoach($certification, $coach, $admin);

        $enrollment = Enrollment::factory()
            ->for($student)
            ->for($certification)
            ->create();

        $room = ChatRoom::factory()
            ->for($enrollment)
            ->create();

        ChatMember::factory()->create([
            'chat_room_id' => $room->id,
            'user_id' => $student->id,
        ]);

        ChatMember::factory()->create([
            'chat_room_id' => $room->id,
            'user_id' => $coach->id,
        ]);

        $messageBody = 'メール通知のテストメッセージです。';

        // Act
        $response = $this->actingAs($student)->post(
            route('chat.storeMessage', $room),
            [
                'body' => $messageBody,
            ],
        );

        // Assert
        $response->assertRedirect(route('chat.show', $room));

        Notification::assertSentTo(
            $coach,
            ChatMessageReceivedNotification::class,
            function (ChatMessageReceivedNotification $notification) use (
                $coach,
                $messageBody,
                $room,
            ): bool {
                $mail = $notification->toMail($coach);

                $this->assertSame(
                    'Certify LMS 新しいメッセージのお知らせ',
                    $mail->subject,
                );

                $this->assertSame(
                    $coach->name.' 様',
                    $mail->greeting,
                );

                $this->assertContains(
                    'チャットに新しいメッセージが届きました。',
                    $mail->introLines,
                );

                $this->assertContains(
                    $messageBody,
                    $mail->introLines,
                );

                $this->assertSame(
                    'チャットを確認する',
                    $mail->actionText,
                );

                $this->assertSame(
                    route('chat.show', $room),
                    $mail->actionUrl,
                );

                return true;
            },
        );
    }
}
