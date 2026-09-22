<?php

declare(strict_types=1);

namespace App\UseCases\Settings;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * アバター画像を保存するアクション。
 */
final class StoreAvatarAction
{
    /**
     * アバター画像を保存する。
     */
    public function __invoke(User $user, UploadedFile $file): void
    {
        $ulid = (string) Str::ulid();
        $ext = strtolower($file->getClientOriginalExtension() ?: 'png');
        $path = "avatars/{$ulid}.{$ext}";
        $url = Storage::disk('public')->url($path);
        $oldUrl = $user->avatar_url;

        try {
            DB::transaction(function () use ($user, $file, $path, $url, $oldUrl): void {
                Storage::disk('public')->putFileAs(
                    'avatars',
                    $file,
                    basename($path),
                );

                $user->update([
                    'avatar_url' => $url,
                ]);

                if ($oldUrl === null || $oldUrl === $url) {
                    return;
                }

                $oldPath = parse_url($oldUrl, PHP_URL_PATH);

                if (! is_string($oldPath)) {
                    return;
                }

                $storagePrefix = '/storage/';

                if (! str_starts_with($oldPath, $storagePrefix)) {
                    return;
                }

                DB::afterCommit(function () use ($oldPath, $storagePrefix): void {
                    Storage::disk('public')->delete(
                        substr($oldPath, strlen($storagePrefix))
                    );
                });
            });
        } catch (\Throwable $e) {
            Storage::disk('public')->delete($path);

            throw $e;
        }
    }
}
