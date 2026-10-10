<?php

declare(strict_types=1);

namespace App\UseCases\GoogleCalendar;

use App\Models\GoogleCredential;
use App\Services\GoogleCalendarService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class CallbackAction
{
    /**
     * Google Calendar OAuth コールバックを処理する。
     *
     * @param Request $request
     * @param GoogleCalendarService $googleCalendarService
     *
     * @return RedirectResponse
     */
    public function __invoke(
        Request $request,
        GoogleCalendarService $googleCalendarService,
    ): RedirectResponse {
        $state = (string) $request->query('state');

        try {
            $userId = Crypt::decryptString($state);
        } catch (\Throwable) {
            throw new BadRequestHttpException('Invalid OAuth state.');
        }

        if ($userId !== (string) $request->user()->id) {
            throw new BadRequestHttpException('Invalid OAuth state.');
        }

        $code = (string) $request->query('code');

        if ($code === '') {
            throw new BadRequestHttpException('Authorization code is missing.');
        }

        $token = $googleCalendarService->exchangeCode($code);

        if (! isset($token['access_token'])) {
            throw new BadRequestHttpException('Failed to obtain Google access token.');
        }

        $calendarId = $googleCalendarService->primaryCalendarId($token);

        $credential = GoogleCredential::firstOrNew([
            'user_id' => (string) $request->user()->id,
        ]);

        $credential->calendar_id = $calendarId;
        $credential->access_token = (string) $token['access_token'];
        $credential->token_expires_at = now()->addSeconds(
            (int) ($token['expires_in'] ?? 3600)
        );
        $credential->connected_at = now();

        if (! empty($token['refresh_token'])) {
            $credential->refresh_token = (string) $token['refresh_token'];
        }

        $credential->save();

        return redirect()
            ->route('settings.availability.index')
            ->with('success', 'Google Calendarとの連携が完了しました。');
    }
}
