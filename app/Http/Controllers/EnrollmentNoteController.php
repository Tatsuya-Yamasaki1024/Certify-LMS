<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\EnrollmentNote\StoreRequest;
use App\Http\Requests\EnrollmentNote\UpdateRequest;
use App\Models\Enrollment;
use App\Models\EnrollmentNote;
use App\UseCases\EnrollmentNote\DestroyAction;
use App\UseCases\EnrollmentNote\StoreAction;
use App\UseCases\EnrollmentNote\UpdateAction;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

final class EnrollmentNoteController extends Controller
{
    /**
     * 受講登録にコーチメモを追加する。
     */
    public function store(
        StoreRequest $request,
        Enrollment $enrollment,
        StoreAction $action,
    ): RedirectResponse {
        $action($enrollment, [
            ...$request->validated(),
            'user_id' => $request->user()->id,
        ]);

        return to_route('enrollments.show', $enrollment)
            ->with('success', 'メモを追加しました。');
    }

    /**
     * コーチメモの編集画面を表示する。
     */
    public function edit(EnrollmentNote $note): View
    {
        $this->authorize('update', $note);

        return view('enrollment-note.edit', [
            'note' => $note,
        ]);
    }

    /**
     * コーチメモを更新する。
     */
    public function update(
        UpdateRequest $request,
        EnrollmentNote $note,
        UpdateAction $action,
    ): RedirectResponse {
        $action($note, $request->validated());

        return to_route('enrollments.show', $note->enrollment_id)
            ->with('success', 'メモを更新しました。');
    }

    /**
     * コーチメモを削除する。
     */
    public function destroy(
        EnrollmentNote $note,
        DestroyAction $action,
    ): RedirectResponse {
        $this->authorize('delete', $note);

        $enrollmentId = $note->enrollment_id;

        $action($note);

        return to_route('enrollments.show', $enrollmentId)
            ->with('success', 'メモを削除しました。');
    }
}
