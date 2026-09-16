<?php

declare(strict_types=1);

namespace App\Notifications\Chat;

use App\Models\ChatMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ChatMessageReceivedNotification extends Notification
{
    public function __construct(
        private readonly ChatMessage $message,
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
            'notification_type' => 'chat_message_received',
            'title' => '新しいメッセージがあります',
            'message' => $this->message->body,
            'chat_room_id' => $this->message->chat_room_id,
        ];
    }

    // メール通知の内容を返す。
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Certify LMS 新しいメッセージのお知らせ')
            ->greeting($notifiable->name.' 様')
            ->line('チャットに新しいメッセージが届きました。')
            ->line($this->message->body)
            ->action(
                'チャットを確認する',
                route('chat.show', $this->message->chat_room_id),
            )
            ->salutation('Certify LMS 運営チーム');
    }
}
