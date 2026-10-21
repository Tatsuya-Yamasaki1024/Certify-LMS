<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Certificate;
use App\Models\User;

/**
 * 修了証のダウンロード認可を担当するポリシー。
 */
class CertificatePolicy
{
    /**
     * 修了証のダウンロードを許可する。
     *
     * 管理者はすべての修了証、受講生は本人の修了証、
     * コーチは現在担当している資格の修了証のみダウンロードできる。
     */
    public function download(User $user, Certificate $certificate): bool
    {
        return match ($user->role) {
            UserRole::Admin => true,
            UserRole::Student => $certificate->user_id === $user->id,
            UserRole::Coach => $this->isAssignedCoach($certificate, $user),
        };
    }

    /**
     * コーチが修了証の資格を現在担当しているか判定する。
     */
    private function isAssignedCoach(Certificate $certificate, User $coach): bool
    {
        return $coach->assignedCertifications()
            ->where('certifications.id', $certificate->certification_id)
            ->exists();
    }
}
