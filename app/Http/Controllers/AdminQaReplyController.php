<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\QaReply;
use App\Models\QaThread;
use Illuminate\Http\RedirectResponse;

class AdminQaReplyController extends Controller
{
    public function destroy(
        QaThread $thread,
        QaReply $reply
    ): RedirectResponse {
        abort_unless($reply->qa_thread_id === $thread->id, 404);

        $reply->delete();

        return back()->with('success', '回答を削除しました。');
    }
}
