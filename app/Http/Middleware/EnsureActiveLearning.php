<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\UserStatus;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 受講中(in_progress)以外のユーザーを対象機能から弾く Middleware。
 *
 * 卒業(graduated)ユーザーはログイン可能だが、学習 / 演習 / 模試 / 面談 / 追加面談購入 / qa-board / chat / ai-chat 等の
 * 受講中ユーザー向け機能には進めない。
 *
 * プロフィール / 修了証 PDF DL / 通知一覧などは引き続き利用可能なため、
 * 本 Middleware は該当ルートグループに対してのみ適用する（全 auth グループには適用しない）。
 */
final class EnsureActiveLearning
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || $user->status !== UserStatus::InProgress) {
            abort(403, '現在のご利用状況では、この機能をご利用いただけません。');
        }

        return $next($request);
    }
}
