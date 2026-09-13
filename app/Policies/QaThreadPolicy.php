<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\CertificationStatus;
use App\Enums\UserRole;
use App\Models\QaThread;
use App\Models\User;

class QaThreadPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array(
            $user->role,
            [UserRole::Admin, UserRole::Coach, UserRole::Student],
            true
        );
    }

    public function view(User $user, QaThread $qaThread): bool
    {
        if ($user->role === UserRole::Admin) {
            return true;
        }

        $certification = $qaThread->certification;

        return match ($user->role) {
            UserRole::Coach => $certification->status === CertificationStatus::Published
                && $certification->coaches->contains('id', $user->id),
            UserRole::Student => $certification->status === CertificationStatus::Published,
            default => false,
        };
    }

    public function create(User $user): bool
    {
        return $user->role === UserRole::Student;
    }

    public function update(User $user, QaThread $qaThread): bool
    {
        return $user->role === UserRole::Student
            && $qaThread->user_id === $user->id
            && $qaThread->certification->status === CertificationStatus::Published;
    }

    public function delete(User $user, QaThread $qaThread): bool
    {
        if ($user->role === UserRole::Admin) {
            return true;
        }

        return $user->role === UserRole::Student
            && $qaThread->user_id === $user->id
            && $qaThread->certification->status === CertificationStatus::Published
            && ! $qaThread->replies()->exists();
    }

    public function resolve(User $user, QaThread $qaThread): bool
    {
        return $user->role === UserRole::Student
            && $qaThread->user_id === $user->id
            && $qaThread->certification->status === CertificationStatus::Published;
    }

    public function unresolve(User $user, QaThread $qaThread): bool
    {
        return $user->role === UserRole::Student
            && $qaThread->user_id === $user->id
            && $qaThread->certification->status === CertificationStatus::Published;
    }
}
