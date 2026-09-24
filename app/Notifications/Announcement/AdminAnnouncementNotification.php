<?php

declare(strict_types=1);

namespace App\Notifications\Announcement;

use App\Models\Announcement;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AdminAnnouncementNotification extends Notification
{
    public function __construct(
        private readonly Announcement $announcement,
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    /**
     * Get the database representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'notification_type' => 'admin_announcement',
            'title' => $this->announcement->title,
            'message' => $this->announcement->body,
            'body' => $this->announcement->body,
            'announcement_id' => $this->announcement->id,
        ];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Certify LMS 運営からのお知らせ：'.$this->announcement->title)
            ->greeting($notifiable->name.' 様')
            ->line($this->announcement->body)
            ->salutation('Certify LMS 運営チーム');
    }
}
