<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Http\Requests\QaBoard\StoreQaReplyRequest;
use App\Http\Requests\QaBoard\UpdateQaReplyRequest;
use App\Models\QaReply;
use App\Models\QaThread;
use App\Notifications\Qa\QaReplyReceivedNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class QaReplyController extends Controller
{
    // 回答を投稿し、質問者に通知する。
    public function store(
        StoreQaReplyRequest $request,
        QaThread $thread
    ): RedirectResponse {
        $this->authorize('create', [QaReply::class, $thread]);

        $reply = $thread->replies()->create([
            'user_id' => $request->user()->id,
            'body' => $request->validated('body'),
        ]);

        if (
            $thread->user_id !== $request->user()->id
            && in_array($thread->user->role, [UserRole::Student, UserRole::Coach], true)
            && $thread->user->status === UserStatus::InProgress
        ) {
            $thread->user->notify(
                new QaReplyReceivedNotification($reply)
            );
        }

        return back()->with('success', '回答を投稿しました。');
    }

    public function edit(
        QaThread $thread,
        QaReply $reply
    ): View {
        abort_unless($reply->qa_thread_id === $thread->id, 404);

        $this->authorize('update', $reply);

        return view('qa-thread.reply-edit', [
            'thread' => $thread,
            'reply' => $reply,
        ]);
    }

    public function update(
        UpdateQaReplyRequest $request,
        QaThread $thread,
        QaReply $reply
    ): RedirectResponse {
        abort_unless($reply->qa_thread_id === $thread->id, 404);

        $this->authorize('update', $reply);

        $reply->update($request->validated());

        return redirect()
            ->route('qa-board.show', $thread)
            ->with('success', '回答を更新しました。');
    }

    public function destroy(
        QaThread $thread,
        QaReply $reply
    ): RedirectResponse {
        abort_unless($reply->qa_thread_id === $thread->id, 404);

        $this->authorize('delete', $reply);

        $reply->delete();

        return back()->with('success', '回答を削除しました。');
    }
}
