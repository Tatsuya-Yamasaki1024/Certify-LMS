<?php

declare(strict_types=1);

namespace App\UseCases\Settings;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * アバター画像を削除するアクション。
 */
final class DestroyAvatarAction
{
    /**
     * アバター画像を削除する。
     */
    public function __invoke(User $user): void
    {
        $avatarUrl = $user->avatar_url;

        if ($avatarUrl === null) {
            return;
        }

        $avatarPath = parse_url($avatarUrl, PHP_URL_PATH);

        if (! is_string($avatarPath)) {
            return;
        }

        $storagePrefix = '/storage/';

        if (! str_starts_with($avatarPath, $storagePrefix)) {
            return;
        }

        $path = substr($avatarPath, strlen($storagePrefix));

        DB::transaction(function () use ($user, $path): void {
            $user->update([
                'avatar_url' => null,
            ]);

            DB::afterCommit(function () use ($path): void {
                Storage::disk('public')->delete($path);
            });
        });
    }
}
