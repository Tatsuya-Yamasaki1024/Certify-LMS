<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Notification;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class IndexTest extends TestCase
{
    use RefreshDatabase;

    // 受講生が自分の通知一覧を表示できることを確認する。
    public function test_student_can_view_own_notifications(): void
    {
        // Arrange
        $student = User::factory()->student()->inProgress()->create();

        $student->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => 'App\Notifications\Chat\ChatMessageReceivedNotification',
            'data' => [
                'notification_type' => 'chat_message_received',
                'title' => '新しいメッセージがあります',
                'message' => '新しいメッセージ本文です。',
            ],
        ]);

        // Act
        $response = $this->actingAs($student)
            ->get(route('notifications.index'));

        // Assert
        $response->assertOk();
        $response->assertViewIs('notifications.index');
        $response->assertSee('新しいメッセージがあります');
        $response->assertSee('新しいメッセージ本文です。');
        $response->assertViewHas('unreadCount', 1);
    }

    // 他のユーザーの通知が一覧に表示されないことを確認する。
    public function test_notification_list_does_not_include_another_users_notifications(): void
    {
        // Arrange
        $student = User::factory()->student()->inProgress()->create();
        $otherStudent = User::factory()->student()->inProgress()->create();

        $student->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => 'App\Notifications\Chat\ChatMessageReceivedNotification',
            'data' => [
                'notification_type' => 'chat_message_received',
                'title' => '自分の通知',
                'message' => '自分だけに表示される通知です。',
            ],
        ]);

        $otherStudent->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => 'App\Notifications\Qa\QaReplyReceivedNotification',
            'data' => [
                'notification_type' => 'qa_reply_received',
                'title' => '他人の通知',
                'message' => '他人には表示されません。',
            ],
        ]);

        // Act
        $response = $this->actingAs($student)
            ->get(route('notifications.index'));

        // Assert
        $response->assertOk();
        $response->assertSee('自分の通知');
        $response->assertDontSee('他人の通知');
        $response->assertDontSee('他人には表示されません。');
    }

    // 未読タブでは未読通知だけが表示されることを確認する。
    public function test_unread_tab_shows_only_unread_notifications(): void
    {
        // Arrange
        $student = User::factory()->student()->inProgress()->create();

        $student->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => 'App\Notifications\Chat\ChatMessageReceivedNotification',
            'data' => [
                'notification_type' => 'chat_message_received',
                'title' => '未読通知',
                'message' => '未読の通知です。',
            ],
        ]);

        $readNotification = $student->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => 'App\Notifications\Qa\QaReplyReceivedNotification',
            'data' => [
                'notification_type' => 'qa_reply_received',
                'title' => '既読通知',
                'message' => '既読の通知です。',
            ],
        ]);

        $readNotification->markAsRead();

        // Act
        $response = $this->actingAs($student)
            ->get(route('notifications.index', ['tab' => 'unread']));

        // Assert
        $response->assertOk();
        $response->assertSee('未読通知');
        $response->assertDontSee('既読通知');
        $response->assertViewHas('tab', 'unread');
        $response->assertViewHas('unreadCount', 1);
    }

    // 不正なタブ指定の場合は全件タブとして扱われることを確認する。
    public function test_invalid_tab_defaults_to_all(): void
    {
        // Arrange
        $student = User::factory()->student()->inProgress()->create();

        $student->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => 'App\Notifications\Chat\ChatMessageReceivedNotification',
            'data' => [
                'notification_type' => 'chat_message_received',
                'title' => '通知タイトル',
                'message' => '通知本文です。',
            ],
        ]);

        // Act
        $response = $this->actingAs($student)
            ->get(route('notifications.index', ['tab' => 'invalid']));

        // Assert
        $response->assertOk();
        $response->assertSee('通知タイトル');
        $response->assertViewHas('tab', 'all');
    }

    // 通知一覧がページネーションされることを確認する。
    public function test_notifications_are_paginated(): void
    {
        // Arrange
        $student = User::factory()->student()->inProgress()->create();

        foreach (range(1, 21) as $number) {
            $student->notifications()->create([
                'id' => (string) Str::uuid(),
                'type' => 'App\Notifications\Chat\ChatMessageReceivedNotification',
                'data' => [
                    'notification_type' => 'chat_message_received',
                    'title' => sprintf('通知 %03d', $number),
                    'message' => sprintf('通知本文 %03d', $number),
                ],
                'created_at' => now()->subMinutes(21 - $number),
            ]);
        }

        // Act
        $response = $this->actingAs($student)
            ->get(route('notifications.index'));

        // Assert
        $response->assertOk();
        $response->assertSee('通知 021');
        $response->assertDontSee('通知 001');

        $response->assertViewHas('notifications', function ($notifications): bool {
            return $notifications->perPage() === 20
                && $notifications->count() === 20
                && $notifications->currentPage() === 1
                && $notifications->hasPages();
        });

        // Act
        $response = $this->actingAs($student)
            ->get(route('notifications.index', ['page' => 2]));

        // Assert
        $response->assertOk();

        $response->assertViewHas('notifications', function ($notifications): bool {
            return $notifications->perPage() === 20
                && $notifications->count() === 1
                && $notifications->currentPage() === 2
                && $notifications->hasPages();
        });

        $response->assertSee('通知 001');
        $response->assertDontSee('通知 021');
    }
}
