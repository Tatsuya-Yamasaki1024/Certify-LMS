<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\GoogleCredential;
use App\Services\GoogleCalendarService;
use App\UseCases\GoogleCalendar\CallbackAction;
use App\UseCases\GoogleCalendar\ConnectAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class GoogleCalendarController extends Controller
{
    /**
     * Google Calendar の OAuth 認証画面へリダイレクトする。
     */
    public function redirect(Request $request, ConnectAction $action): RedirectResponse
    {
        $url = $action((string) $request->user()->id);

        return redirect()->away($url);
    }

    /**
     * Google Calendar の OAuth コールバックを処理する。
     */
    public function callback(
        Request $request,
        CallbackAction $action,
        GoogleCalendarService $googleCalendarService,
    ): RedirectResponse {
        return $action($request, $googleCalendarService);
    }

    /**
     * Google Calendar の連携を解除する。
     */
    public function destroy(Request $request): RedirectResponse
    {
        GoogleCredential::where('user_id', $request->user()->id)->delete();

        return redirect()
            ->route('settings.availability.index')
            ->with('success', 'Google Calendarとの連携を解除しました。');
    }
}
