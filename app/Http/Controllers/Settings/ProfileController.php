<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Actions\Fortify\UpdateUserPassword;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\StoreAvatarRequest;
use App\Http\Requests\Settings\UpdateProfileRequest;
use App\UseCases\Settings\DestroyAvatarAction;
use App\UseCases\Settings\StoreAvatarAction;
use App\UseCases\Settings\UpdateProfileAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * プロフィール設定を管理するコントローラー。
 */
class ProfileController extends Controller
{
    /**
     * プロフィール設定画面を表示する。
     */
    public function edit(Request $request): View
    {
        return view('settings.profile', [
            'user' => $request->user(),
        ]);
    }

    /**
     * プロフィールを更新する。
     */
    public function update(
        UpdateProfileRequest $request,
        UpdateProfileAction $action
    ): RedirectResponse {
        $action($request->user(), $request->validated());

        return redirect()
            ->route('settings.profile.edit')
            ->with('success', 'プロフィールを更新しました。');
    }

    /**
     * アバター画像を保存する。
     */
    public function storeAvatar(
        StoreAvatarRequest $request,
        StoreAvatarAction $action
    ): RedirectResponse {
        $action($request->user(), $request->file('avatar'));

        return redirect()
            ->route('settings.profile.edit')
            ->with('success', 'プロフィール画像を更新しました。');
    }

    /**
     * アバター画像を削除する。
     */
    public function destroyAvatar(
        Request $request,
        DestroyAvatarAction $action
    ): RedirectResponse {
        $action($request->user());

        return redirect()
            ->route('settings.profile.edit')
            ->with('success', 'プロフィール画像を削除しました。');
    }

    /**
     * パスワードを更新する。
     */
    public function updatePassword(
        Request $request,
        UpdateUserPassword $action
    ): RedirectResponse {
        $action->update($request->user(), $request->only([
            'current_password',
            'password',
            'password_confirmation',
        ]));

        return redirect()
            ->route('settings.profile.edit', ['tab' => 'password'])
            ->with('success', 'パスワードを変更しました。');
    }
}
