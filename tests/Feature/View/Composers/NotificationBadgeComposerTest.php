<?php

declare(strict_types=1);

namespace Tests\Feature\View\Composers;

use App\Models\User;
use App\View\Composers\NotificationBadgeComposer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
use Tests\TestCase;

class NotificationBadgeComposerTest extends TestCase
{
    use RefreshDatabase;

    // 未読通知数と通知バッジの表示件数が一致することを確認する。
    public function test_notification_badge_matches_unread_notification_count(): void
    {
        // Arrange
        $student = User::factory()->student()->inProgress()->create();

        $student->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => 'App\Notifications\Chat\ChatMessageReceivedNotification',
            'data' => [
                'notification_type' => 'chat_message_received',
                'title' => '未読通知1',
                'message' => '未読通知です。',
            ],
        ]);

        $student->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => 'App\Notifications\Qa\QaReplyReceivedNotification',
            'data' => [
                'notification_type' => 'qa_reply_received',
                'title' => '未読通知2',
                'message' => '未読通知です。',
            ],
        ]);

        $readNotification = $student->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => 'App\Notifications\Qa\QaReplyReceivedNotification',
            'data' => [
                'notification_type' => 'qa_reply_received',
                'title' => '既読通知',
                'message' => '既読通知です。',
            ],
        ]);

        $readNotification->markAsRead();

        $view = View::make('layouts._partials.topbar');

        Route::shouldReceive('has')
            ->with('notifications.index')
            ->andReturnTrue();

        $this->actingAs($student);

        // Act
        app(NotificationBadgeComposer::class)->compose($view);

        // Assert
        $this->assertSame(
            2,
            $view->getData()['notificationBadge'],
        );
    }

    // 通知を既読にすると通知バッジの表示件数が減ることを確認する。
    public function test_notification_badge_decreases_when_notification_is_read(): void
    {
        // Arrange
        $student = User::factory()->student()->inProgress()->create();

        $notification = $student->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => 'App\Notifications\Chat\ChatMessageReceivedNotification',
            'data' => [
                'notification_type' => 'chat_message_received',
                'title' => '未読通知',
                'message' => '未読通知です。',
            ],
        ]);

        $student->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => 'App\Notifications\Qa\QaReplyReceivedNotification',
            'data' => [
                'notification_type' => 'qa_reply_received',
                'title' => '未読通知2',
                'message' => '未読通知です。',
            ],
        ]);

        $this->actingAs($student);

        Route::shouldReceive('has')
            ->with('notifications.index')
            ->andReturnTrue();

        $view = View::make('layouts._partials.topbar');

        // Act
        app(NotificationBadgeComposer::class)->compose($view);

        $this->assertSame(
            2,
            $view->getData()['notificationBadge'],
        );

        $notification->markAsRead();

        $view = View::make('layouts._partials.topbar');

        app(NotificationBadgeComposer::class)->compose($view);

        // Assert
        $this->assertSame(
            1,
            $view->getData()['notificationBadge'],
        );
    }
}
