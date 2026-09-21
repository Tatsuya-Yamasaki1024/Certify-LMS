<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\EnrollmentGoal\StoreRequest;
use App\Http\Requests\EnrollmentGoal\UpdateRequest;
use App\Models\Enrollment;
use App\Models\EnrollmentGoal;
use App\UseCases\EnrollmentGoal\DestroyAction;
use App\UseCases\EnrollmentGoal\MarkAchievedAction;
use App\UseCases\EnrollmentGoal\StoreAction;
use App\UseCases\EnrollmentGoal\UnmarkAchievedAction;
use App\UseCases\EnrollmentGoal\UpdateAction;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

final class EnrollmentGoalController extends Controller
{
    /**
     * 個人学習目標を作成する。
     */
    public function store(
        StoreRequest $request,
        Enrollment $enrollment,
        StoreAction $action,
    ): RedirectResponse {
        $action($enrollment, $request->validated());

        return to_route('enrollments.show', $enrollment);
    }

    /**
     * 個人学習目標の編集画面を表示する。
     */
    public function edit(EnrollmentGoal $goal): View
    {
        $this->authorize('update', $goal);

        return view('enrollment-goal.edit', [
            'goal' => $goal,
        ]);
    }

    /**
     * 個人学習目標を更新する。
     */
    public function update(
        UpdateRequest $request,
        EnrollmentGoal $goal,
        UpdateAction $action,
    ): RedirectResponse {
        $action($goal, $request->validated());

        return to_route('enrollments.show', $goal->enrollment_id);
    }

    /**
     * 個人学習目標を削除する。
     */
    public function destroy(
        EnrollmentGoal $goal,
        DestroyAction $action,
    ): RedirectResponse {
        $this->authorize('delete', $goal);

        $enrollmentId = $goal->enrollment_id;

        $action($goal);

        return to_route('enrollments.show', $enrollmentId);
    }

    /**
     * 個人学習目標を達成済みにする。
     */
    public function markAchieved(
        EnrollmentGoal $goal,
        MarkAchievedAction $action,
    ): RedirectResponse {
        $this->authorize('markAchieved', $goal);

        $action($goal);

        return to_route('enrollments.show', $goal->enrollment_id);
    }

    /**
     * 個人学習目標を未達成に戻す。
     */
    public function unmarkAchieved(
        EnrollmentGoal $goal,
        UnmarkAchievedAction $action,
    ): RedirectResponse {
        $this->authorize('unmarkAchieved', $goal);

        $action($goal);

        return to_route('enrollments.show', $goal->enrollment_id);
    }
}
