<?php

declare(strict_types=1);

namespace App\UseCases\GoogleCalendar;

use App\Services\GoogleCalendarService;
use Illuminate\Support\Facades\Crypt;

class ConnectAction
{
    /**
     * Google Calendar OAuth 認証URLを生成する。
     *
     * @param string $userId
     *
     * @return string
     */
    public function __invoke(string $userId): string
    {
        $state = Crypt::encryptString($userId);

        return app(GoogleCalendarService::class)->authorizationUrl($state);
    }
}
