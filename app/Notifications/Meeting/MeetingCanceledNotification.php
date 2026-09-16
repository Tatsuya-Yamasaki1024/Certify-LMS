<?php

declare(strict_types=1);

namespace App\Notifications\Meeting;

use App\Models\Meeting;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MeetingCanceledNotification extends Notification
{
    public function __construct(
        private readonly Meeting $meeting,
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
            'notification_type' => 'meeting_canceled',
            'title' => '面談がキャンセルされました',
            'message' => '面談予約がキャンセルされました。',
            'meeting_id' => $this->meeting->id,
        ];
    }

    // メール通知の内容を返す。
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Certify LMS 面談キャンセルのお知らせ')
            ->greeting($notifiable->name.' 様')
            ->line('面談予約がキャンセルされました。')
            ->line('予約日時：'.$this->meeting->scheduled_at->format('Y年m月d日 H:i'))
            ->action(
                '面談を確認する',
                route('meetings.show', $this->meeting),
            )
            ->salutation('Certify LMS 運営チーム');
    }
}
