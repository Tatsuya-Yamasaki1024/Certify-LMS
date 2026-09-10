<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\CertificationStatus;
use App\Enums\QaThreadStatus;
use App\Models\Certification;
use App\Models\QaThread;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminQaThreadController extends Controller
{
    public function index(Request $request): View
    {
        $threads = QaThread::query()
            ->with(['user', 'certification'])
            ->withCount('replies')
            ->when(
                $request->filled('certification_id'),
                fn ($query) => $query->where(
                    'certification_id',
                    $request->input('certification_id')
                )
            )
            ->when(
                $request->input('status') === QaThreadStatus::Resolved->value,
                fn ($query) => $query->where(
                    'status',
                    QaThreadStatus::Resolved
                )
            )
            ->when(
                $request->input('status') === QaThreadStatus::Unresolved->value,
                fn ($query) => $query->where(
                    'status',
                    QaThreadStatus::Unresolved
                )
            )
            ->when(
                $request->filled('keyword'),
                fn ($query) => $query->where(
                    'body',
                    'like',
                    '%'.$request->input('keyword').'%'
                )
            )
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('qa-thread.index', [
            'threads' => $threads,
            'filters' => [
                'status' => $request->input('status', ''),
                'certification_id' => $request->input('certification_id', ''),
                'keyword' => $request->input('keyword', ''),
            ],
            'certifications' => Certification::query()
                ->orderBy('name')
                ->get(),
            'publishedStatus' => CertificationStatus::Published,
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
