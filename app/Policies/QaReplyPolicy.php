<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\CertificationStatus;
use App\Enums\UserRole;
use App\Models\QaReply;
use App\Models\QaThread;
use App\Models\User;

class QaReplyPolicy
{
    public function create(User $user, QaThread $qaThread): bool
    {
        if (! in_array($user->role, [UserRole::Student, UserRole::Coach], true)) {
            return false;
        }

        if ($qaThread->certification->status !== CertificationStatus::Published) {
            return false;
        }

        if ($user->role === UserRole::Coach) {
            return $qaThread->certification->coaches->contains('id', $user->id);
        }

        return true;
    }

    public function update(User $user, QaReply $qaReply): bool
    {
        return $user->role !== UserRole::Admin
            && $qaReply->user_id === $user->id;
    }

    public function delete(User $user, QaReply $qaReply): bool
    {
        return $user->role === UserRole::Admin
            || $qaReply->user_id === $user->id;
    }
}
