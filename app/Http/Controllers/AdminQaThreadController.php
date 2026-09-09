<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\QaThread;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class AdminQaThreadController extends Controller
{
    public function index(): View
    {
        $threads = QaThread::query()
            ->with(['user', 'certification'])
            ->withCount('replies')
            ->latest()
            ->paginate(10);

        return view('qa-thread.index', [
            'threads' => $threads,
            'filters' => [
                'status' => '',
                'certification_id' => '',
                'keyword' => '',
            ],
            'certifications' => collect(),
        ]);
    }

    public function show(QaThread $thread): View
    {
        $thread->load([
            'user',
            'certification',
            'replies.user',
        ]);

        return view('qa-thread.show', [
            'thread' => $thread,
        ]);
    }

    public function destroy(QaThread $thread): RedirectResponse
    {
        $thread->delete();

        return redirect()
            ->route('admin.qa-board.index')
            ->with('success', '質問を削除しました。');
    }
}
