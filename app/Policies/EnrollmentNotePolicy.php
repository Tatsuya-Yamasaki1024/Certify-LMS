<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Enrollment;
use App\Models\EnrollmentNote;
use App\Models\User;

/**
 * 受講登録に紐づくコーチメモに対する認可ポリシー。
 *
 * - Admin: すべての受講登録のメモを閲覧・追加・編集・削除できる
 * - Coach: 担当資格の受講登録のみ閲覧・追加でき、自分のメモのみ編集・削除できる
 * - Student: メモを閲覧・追加・編集・削除できない
 *
 * コーチが資格の担当から外れた場合は、過去に自身が作成したメモも操作できない。
 */
class EnrollmentNotePolicy
{
    /**
     * 受講登録のメモ一覧を閲覧できるか判定する。
     */
    public function viewAny(User $user, Enrollment $enrollment): bool
    {
        return match ($user->role) {
            UserRole::Admin => true,
            UserRole::Coach => $this->isAssignedCoach($enrollment, $user),
            default => false,
        };
    }

    /**
     * 受講登録にメモを追加できるか判定する。
     */
    public function create(User $user, Enrollment $enrollment): bool
    {
        return $this->viewAny($user, $enrollment);
    }

    /**
     * メモを編集できるか判定する。
     */
    public function update(User $user, EnrollmentNote $note): bool
    {
        if ($user->role === UserRole::Admin) {
            return true;
        }

        if ($user->role !== UserRole::Coach || $note->user_id !== $user->id) {
            return false;
        }

        $note->loadMissing('enrollment.certification.coaches');

        return $this->isAssignedCoach($note->enrollment, $user);
    }

    /**
     * メモを削除できるか判定する。
     */
    public function delete(User $user, EnrollmentNote $note): bool
    {
        return $this->update($user, $note);
    }

    /**
     * コーチが資格の担当者か判定する。
     */
    private function isAssignedCoach(Enrollment $enrollment, User $coach): bool
    {
        $enrollment->loadMissing('certification.coaches');

        return $enrollment->certification?->coaches->contains('id', $coach->id) ?? false;
    }
}
