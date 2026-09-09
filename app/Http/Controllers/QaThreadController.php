<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\QaThreadStatus;
use App\Enums\UserRole;
use App\Enums\CertificationStatus;
use App\Http\Requests\QaBoard\StoreQaThreadRequest;
use App\Http\Requests\QaBoard\UpdateQaThreadRequest;
use App\Models\Certification;
use App\Models\QaThread;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class QaThreadController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        $threads = QaThread::query()
            ->with(['user', 'certification'])
            ->withCount('replies')
            ->whereHas('certification', function ($query) {
                $query->where('status', CertificationStatus::Published);
            })
            ->when(
                $user->role === UserRole::Coach,
                fn($query) => $query->whereHas(
                    'certification.coaches',
                    fn($coachQuery) => $coachQuery->where('users.id', $user->id)
                )
            )
            ->when(
                $request->filled('certification_id'),
                fn($query) => $query->where(
                    'certification_id',
                    $request->input('certification_id')
                )
            )
            ->when(
                $request->input('status') === QaThreadStatus::Resolved->value,
                fn($query) => $query->where(
                    'status',
                    QaThreadStatus::Resolved
                )
            )
            ->when(
                $request->input('status') === QaThreadStatus::Unresolved->value,
                fn($query) => $query->where(
                    'status',
                    QaThreadStatus::Unresolved
                )
            )
            ->when(
                $request->filled('keyword'),
                fn($query) => $query->where(
                    'body',
                    'like',
                    '%' . $request->input('keyword') . '%'
                )
            )
            ->latest()
            ->paginate(10)
            ->withQueryString();

        $certifications = Certification::query()
            ->where('status', CertificationStatus::Published)
            ->orderBy('name')
            ->get();

        return view('qa-thread.index', [
            'threads' => $threads,
            'filters' => [
                'status' => $request->input('status', ''),
                'certification_id' => $request->input('certification_id', ''),
                'keyword' => $request->input('keyword', ''),
            ],
            'certifications' => $certifications,
            'publishedStatus' => CertificationStatus::Published,
        ]);
    }

    public function create(): View
    {
        $certifications = Certification::query()
            ->where('status', CertificationStatus::Published)->orderBy('name')
            ->get();

        return view('qa-thread.create', [
            'certifications' => $certifications,
        ]);
    }

    public function store(StoreQaThreadRequest $request): RedirectResponse
    {
        $this->authorize('create', QaThread::class);

        $thread = QaThread::create([
            'user_id' => $request->user()->id,
            'certification_id' => $request->validated('certification_id'),
            'title' => $request->validated('title'),
            'body' => $request->validated('body'),
        ]);

        return redirect()
            ->route('qa-board.show', $thread)
            ->with('success', '質問を投稿しました。');
    }

    public function show(QaThread $thread): View
    {
        $this->authorize('view', $thread);

        $thread->load([
            'user',
            'certification.coaches',
            'replies.user',
        ]);

        return view('qa-thread.show', [
            'thread' => $thread,
        ]);
    }

    public function edit(QaThread $thread): View
    {
        $this->authorize('update', $thread);

        $thread->load('certification');

        return view('qa-thread.edit', [
            'thread' => $thread,
        ]);
    }

    public function update(
        UpdateQaThreadRequest $request,
        QaThread $thread
    ): RedirectResponse {
        $this->authorize('update', $thread);

        $thread->update($request->validated());

        return redirect()
            ->route('qa-board.show', $thread)
            ->with('success', '質問を更新しました。');
    }

    public function destroy(QaThread $thread): RedirectResponse
    {
        $this->authorize('delete', $thread);

        $thread->delete();

        return redirect()
            ->route('qa-board.index')
            ->with('success', '質問を削除しました。');
    }

    public function resolve(QaThread $thread): RedirectResponse
    {
        $this->authorize('resolve', $thread);

        $thread->update([
            'status' => 'resolved',
            'resolved_at' => now(),
        ]);

        return back()->with('success', '質問を解決済みにしました。');
    }

    public function unresolve(QaThread $thread): RedirectResponse
    {
        $this->authorize('unresolve', $thread);

        $thread->update([
            'status' => 'unresolved',
            'resolved_at' => null,
        ]);

        return back()->with('success', '質問を未解決に戻しました。');
    }
}
