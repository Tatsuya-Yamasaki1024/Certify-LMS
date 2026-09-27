<?php

declare(strict_types=1);

namespace Tests\Feature\Notifications;

use App\Models\Certification;
use App\Models\QaReply;
use App\Models\QaThread;
use App\Models\User;
use App\Notifications\Qa\QaReplyReceivedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class QaReplyReceivedNotificationTest extends TestCase
{
    use RefreshDatabase;

    // 他の受講生が回答した場合、質問者に通知されることを確認する。
    public function test_qa_reply_notifies_thread_owner(): void
    {
        // Arrange
        Notification::fake();

        $threadOwner = User::factory()
            ->student()
            ->inProgress()
            ->create();

        $replyUser = User::factory()
            ->student()
            ->inProgress()
            ->create();

        $certification = Certification::factory()
            ->published()
            ->create();

        $thread = QaThread::factory()->create([
            'user_id' => $threadOwner->id,
            'certification_id' => $certification->id,
        ]);

        // Act
        $response = $this->actingAs($replyUser)->post(
            route('qa-board.replies.store', $thread),
            [
                'body' => '回答本文です。',
            ],
        );

        // Assert
        $response->assertRedirect();

        $reply = QaReply::query()
            ->where('qa_thread_id', $thread->id)
            ->latest()
            ->firstOrFail();

        Notification::assertSentTo(
            $threadOwner,
            QaReplyReceivedNotification::class,
            function (QaReplyReceivedNotification $notification) use ($threadOwner, $reply): bool {
                $data = $notification->toDatabase($threadOwner);

                return $data['notification_type'] === 'qa_reply_received'
                    && $data['message'] === $reply->body
                    && $data['qa_thread_id'] === $reply->qa_thread_id;
            },
        );
    }

    // 質問者自身が回答した場合、自分には通知されないことを確認する。
    public function test_qa_reply_does_not_notify_reply_author_when_reply_author_is_thread_owner(): void
    {
        // Arrange
        Notification::fake();

        $threadOwner = User::factory()
            ->student()
            ->inProgress()
            ->create();

        $certification = Certification::factory()
            ->published()
            ->create();

        $thread = QaThread::factory()->create([
            'user_id' => $threadOwner->id,
            'certification_id' => $certification->id,
        ]);

        // Act
        $response = $this->actingAs($threadOwner)->post(
            route('qa-board.replies.store', $thread),
            [
                'body' => '自分で回答します。',
            ],
        );

        // Assert
        $response->assertRedirect();

        Notification::assertNotSentTo(
            $threadOwner,
            QaReplyReceivedNotification::class,
        );
    }

    // Q&A回答通知メールの件名、本文、確認リンクが正しいことを確認する。
    public function test_qa_reply_notification_mail_content_is_correct(): void
    {
        // Arrange
        Notification::fake();

        $threadOwner = User::factory()
            ->student()
            ->inProgress()
            ->create();

        $replyUser = User::factory()
            ->student()
            ->inProgress()
            ->create();

        $certification = Certification::factory()
            ->published()
            ->create();

        $thread = QaThread::factory()->create([
            'user_id' => $threadOwner->id,
            'certification_id' => $certification->id,
        ]);

        $replyBody = 'メール通知のテスト回答です。';

        // Act
        $response = $this->actingAs($replyUser)->post(
            route('qa-board.replies.store', $thread),
            [
                'body' => $replyBody,
            ],
        );

        // Assert
        $response->assertRedirect();

        Notification::assertSentTo(
            $threadOwner,
            QaReplyReceivedNotification::class,
            function (QaReplyReceivedNotification $notification) use (
                $threadOwner,
                $replyBody,
                $thread,
            ): bool {
                $mail = $notification->toMail($threadOwner);

                $this->assertSame(
                    'Certify LMS 質問への回答のお知らせ',
                    $mail->subject,
                );

                $this->assertSame(
                    $threadOwner->name.' 様',
                    $mail->greeting,
                );

                $this->assertContains(
                    'あなたの質問に新しい回答が投稿されました。',
                    $mail->introLines,
                );

                $this->assertContains(
                    $replyBody,
                    $mail->introLines,
                );

                $this->assertSame(
                    '質問を確認する',
                    $mail->actionText,
                );

                $this->assertSame(
                    route('qa-board.show', $thread),
                    $mail->actionUrl,
                );

                return true;
            },
        );
    }
}
