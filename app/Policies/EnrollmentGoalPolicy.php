<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\EnrollmentStatus;
use App\Enums\UserRole;
use App\Models\Enrollment;
use App\Models\EnrollmentGoal;
use App\Models\User;

/**
 * 個人学習目標に対する認可ポリシー。
 *
 * - create: 自分の受講中 Enrollment に対してのみ作成可能
 * - update: 自分の受講中 Enrollment に紐づく目標のみ編集可能
 * - delete: 自分の受講中 Enrollment に紐づく目標のみ削除可能
 * - markAchieved: 自分の受講中 Enrollment に紐づく目標のみ達成可能
 * - unmarkAchieved: 自分の受講中 Enrollment に紐づく目標のみ未達成へ戻せる
 */
class EnrollmentGoalPolicy
{
    /**
     * 目標を作成できるか判定する。
     */
    public function create(User $user, Enrollment $enrollment): bool
    {
        return $this->canOperate($user, $enrollment);
    }

    /**
     * 目標を編集できるか判定する。
     */
    public function update(User $user, EnrollmentGoal $goal): bool
    {
        return $this->canOperate($user, $goal->enrollment);
    }

    /**
     * 目標を削除できるか判定する。
     */
    public function delete(User $user, EnrollmentGoal $goal): bool
    {
        return $this->canOperate($user, $goal->enrollment);
    }

    /**
     * 目標を達成済みに変更できるか判定する。
     */
    public function markAchieved(User $user, EnrollmentGoal $goal): bool
    {
        return $this->canOperate($user, $goal->enrollment);
    }

    /**
     * 目標を未達成に戻せるか判定する。
     */
    public function unmarkAchieved(User $user, EnrollmentGoal $goal): bool
    {
        return $this->canOperate($user, $goal->enrollment);
    }

    /**
     * 目標を操作できる受講登録か判定する。
     */
    private function canOperate(User $user, Enrollment $enrollment): bool
    {
        return $user->role === UserRole::Student
            && $enrollment->user_id === $user->id
            && $enrollment->status === EnrollmentStatus::Learning;
    }
}
