<?php

declare(strict_types=1);

namespace App\Notifications\Meeting;

use App\Enums\MeetingReminderType;
use App\Models\Meeting;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MeetingReminderNotification extends Notification
{
    public function __construct(
        private readonly Meeting $meeting,
        private readonly MeetingReminderType $reminderType,
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
            'notification_type' => 'meeting_reminder',
            'title' => $this->title(),
            'message' => $this->message(),
            'meeting_id' => $this->meeting->id,
            'reminder_type' => $this->reminderType->value,
        ];
    }

    // メール通知の内容を返す。
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Certify LMS '.$this->title())
            ->greeting($notifiable->name.' 様')
            ->line($this->message())
            ->line('予約日時：'.$this->meeting->scheduled_at->format('Y年m月d日 H:i'))
            ->action(
                '面談を確認する',
                route('meetings.show', $this->meeting),
            )
            ->salutation('Certify LMS 運営チーム');
    }

    // リマインダー種別に応じたタイトルを返す。
    private function title(): string
    {
        return match ($this->reminderType) {
            MeetingReminderType::Eve => '明日の面談のお知らせ',
            MeetingReminderType::OneHourBefore => 'まもなく面談の時間です',
        };
    }

    // リマインダー種別に応じたメッセージを返す。
    private function message(): string
    {
        return match ($this->reminderType) {
            MeetingReminderType::Eve => '明日、面談の予定があります。',
            MeetingReminderType::OneHourBefore => '1時間後に面談の予定があります。',
        };
    }
}
