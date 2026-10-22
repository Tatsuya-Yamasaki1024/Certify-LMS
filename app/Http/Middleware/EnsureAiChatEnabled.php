<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureAiChatEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(
            config('ai-chat.enabled', false),
            503,
            'AI チャットは現在利用できません。',
        );

        return $next($request);
    }
}
