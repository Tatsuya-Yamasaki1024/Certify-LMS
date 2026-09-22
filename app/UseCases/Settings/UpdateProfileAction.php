<?php

declare(strict_types=1);

namespace App\UseCases\Settings;

use App\Enums\UserRole;
use App\Models\User;

/**
 * プロフィールを更新するアクション。
 */
class UpdateProfileAction
{
    /**
     * プロフィールを更新する。
     *
     * @param array<string, mixed> $data
     */
    public function __invoke(User $user, array $data): void
    {
        $attributes = [
            'name' => $data['name'],
            'bio' => $data['bio'] ?? null,
        ];

        if ($user->role === UserRole::Coach) {
            $attributes['meeting_url'] = $data['meeting_url'] ?? null;
        }

        $user->update($attributes);
    }
}
