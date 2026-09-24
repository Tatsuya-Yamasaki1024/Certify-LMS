<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Http\Requests\Announcement\StoreRequest;
use App\Models\Announcement;
use App\Models\Certification;
use App\Models\User;
use App\UseCases\Announcement\IndexAction;
use App\UseCases\Announcement\ShowAction;
use App\UseCases\Announcement\StoreAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class AnnouncementController extends Controller
{
    /**
     * お知らせ配信履歴を表示する。
     */
    public function index(IndexAction $action): View
    {
        $this->authorize('viewAny', Announcement::class);

        return view('announcement.management.index', [
            'announcements' => $action(),
        ]);
    }

    /**
     * お知らせの詳細を表示する。
     */
    public function show(
        Announcement $announcement,
        ShowAction $action,
    ): View {
        $this->authorize('view', $announcement);

        return view('announcement.management.show', [
            'announcement' => $action($announcement),
        ]);
    }

    /**
     * お知らせ配信画面を表示する。
     */
    public function create(): View
    {
        $this->authorize('create', Announcement::class);

        return view('announcement.management.create', [
            'certifications' => Certification::query()
                ->orderBy('name')
                ->get(),
            'students' => User::query()
                ->where('role', UserRole::Student->value)
                ->where('status', UserStatus::InProgress->value)
                ->orderBy('name')
                ->get(),
        ]);
    }

    /**
     * お知らせを作成して配信する。
     */
    public function store(
        StoreRequest $request,
        StoreAction $action,
    ): RedirectResponse {
        $announcement = $action(
            $request->user(),
            $request->validated(),
        );

        return redirect()
            ->route('admin.announcements.show', $announcement)
            ->with('success', 'お知らせを配信しました。');
    }
}
