<?php

declare(strict_types=1);

namespace App\Notifications\Qa;

use App\Models\QaReply;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class QaReplyReceivedNotification extends Notification
{
    public function __construct(
        private readonly QaReply $reply,
    ) {}

    // 通知の配信先を指定する。
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    // データベース通知に保存するデータを返す。
    public function toDatabase(object $notifiable): array
    {
        return [
            'notification_type' => 'qa_reply_received',
            'title' => '質問に回答が投稿されました',
            'message' => $this->reply->body,
            'qa_thread_id' => $this->reply->qa_thread_id,
        ];
    }

    // メール通知の内容を返す。
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Certify LMS 質問への回答のお知らせ')
            ->greeting($notifiable->name.' 様')
            ->line('あなたの質問に新しい回答が投稿されました。')
            ->line($this->reply->body)
            ->action(
                '質問を確認する',
                route('qa-board.show', $this->reply->qa_thread_id),
            )
            ->salutation('Certify LMS 運営チーム');
    }
}
