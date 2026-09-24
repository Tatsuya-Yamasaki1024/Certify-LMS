<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\View\View;

class NotificationController extends Controller
{
    // 通知一覧を表示する。
    public function index(Request $request): View
    {
        $user = $request->user();
        $tab = $request->string('tab', 'all')->toString();

        if (! in_array($tab, ['all', 'unread'], true)) {
            $tab = 'all';
        }

        $query = $user->notifications()->latest();

        if ($tab === 'unread') {
            $query->whereNull('read_at');
        }

        $notifications = $query
            ->paginate(20)
            ->withQueryString();

        $unreadCount = $user->unreadNotifications()->count();

        return view('notifications.index', [
            'notifications' => $notifications,
            'unreadCount' => $unreadCount,
            'tab' => $tab,
        ]);
    }

    // 自分の通知を既読にし、通知詳細を表示する。
    public function show(
        Request $request,
        DatabaseNotification $notification,
    ): View {
        $notification = $request->user()
            ->notifications()
            ->findOrFail($notification->id);

        $notification->markAsRead();

        return view('notifications.show', [
            'notification' => $notification,
        ]);
    }

    // 自分の通知を既読にし、通知に紐づく画面へ遷移する。
    public function markAsRead(
        Request $request,
        DatabaseNotification $notification,
    ): RedirectResponse {
        $notification = $request->user()
            ->notifications()
            ->findOrFail($notification->id);

        $notification->markAsRead();

        $data = is_array($notification->data) ? $notification->data : [];

        return match ($data['notification_type'] ?? null) {
            'chat_message_received' => redirect()->route(
                'chat.show',
                $data['chat_room_id'],
            ),
            'qa_reply_received' => redirect()->route(
                'qa-board.show',
                $data['qa_thread_id'],
            ),
            'meeting_reserved', 'meeting_canceled', 'meeting_reminder' => redirect()->route(
                'meetings.show',
                $data['meeting_id'],
            ),
            'admin_announcement' => redirect()->route(
                'notifications.show',
                $notification,
            ),
            default => redirect()->route('notifications.index'),
        };
    }

    // 自分の未読通知をすべて既読にする。
    public function markAllAsRead(Request $request): RedirectResponse
    {
        $request->user()
            ->unreadNotifications
            ->markAsRead();

        return redirect()
            ->route('notifications.index')
            ->with('success', '通知をすべて既読にしました。');
    }
}
