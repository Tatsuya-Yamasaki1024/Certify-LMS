<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\GoogleCredential;
use Carbon\Carbon;
use Google\Client as GoogleClient;
use Google\Service\Calendar;
use Google\Service\Calendar\Event;
use Google\Service\Calendar\EventDateTime;
use Google\Service\Calendar\FreeBusyRequest;
use GuzzleHttp\ClientInterface;

class GoogleCalendarService
{
    private ?ClientInterface $httpClient = null;

    /**
     * テスト用HTTPクライアントを設定する。
     *
     * @param ClientInterface $httpClient
     *
     * @return void
     */
    public function setHttpClient(ClientInterface $httpClient): void
    {
        $this->httpClient = $httpClient;
    }

    /**
     * Google API クライアントを生成する。
     *
     * @return GoogleClient
     */
    private function createClient(): GoogleClient
    {
        $client = new GoogleClient;

        if ($this->httpClient !== null) {
            $client->setHttpClient($this->httpClient);
        }

        $client->setClientId((string) config('services.google.client_id'));
        $client->setClientSecret((string) config('services.google.client_secret'));
        $client->setRedirectUri((string) config('services.google.redirect_uri'));
        $client->setAccessType('offline');
        $client->setPrompt('consent');

        $client->setScopes([
            'https://www.googleapis.com/auth/calendar',
        ]);

        return $client;
    }

    /**
     * Google OAuth の認証URLを生成する。
     *
     * @param string $state
     *
     * @return string
     */
    public function authorizationUrl(string $state): string
    {
        $client = $this->createClient();
        $client->setState($state);

        return $client->createAuthUrl();
    }

    /**
     * Google OAuth の認証コードをアクセストークンへ交換する。
     *
     * @param string $code
     *
     * @return array<string, mixed>
     */
    public function exchangeCode(string $code): array
    {
        $client = $this->createClient();

        return $client->fetchAccessTokenWithAuthCode($code);
    }

    /**
     * Google OAuth トークンから primary Calendar のIDを取得する。
     *
     * @param array<string, mixed> $token
     *
     * @return string
     */
    public function primaryCalendarId(array $token): string
    {
        $client = $this->createClient();
        $client->setAccessToken($token);

        $service = new Calendar($client);
        $calendar = $service->calendars->get('primary');

        return (string) $calendar->getId();
    }

    /**
     * Google Calendar の Busy 時間帯を取得する。
     *
     * @param GoogleCredential $credential
     * @param Carbon $start
     * @param Carbon $end
     *
     * @return array<int, array{start: Carbon, end: Carbon}>
     */
    public function busyIntervals(
        GoogleCredential $credential,
        Carbon $start,
        Carbon $end,
    ): array {
        $client = $this->createAuthorizedClient($credential);
        $service = new Calendar($client);

        $request = new FreeBusyRequest([
            'timeMin' => $start->toRfc3339String(),
            'timeMax' => $end->toRfc3339String(),
            'timeZone' => config('app.timezone'),
            'items' => [
                [
                    'id' => $credential->calendar_id,
                ],
            ],
        ]);

        $response = $service->freebusy->query($request);
        $calendar = $response->getCalendars()[$credential->calendar_id] ?? null;

        if ($calendar === null) {
            return [];
        }

        return collect($calendar->getBusy() ?? [])
            ->map(fn ($busy): array => [
                'start' => Carbon::parse($busy->getStart()),
                'end' => Carbon::parse($busy->getEnd()),
            ])
            ->all();
    }

    /**
     * Google Calendar に面談予定を登録する。
     *
     * @param GoogleCredential $credential
     * @param string $studentName
     * @param string $certificationName
     * @param string $topic
     * @param string $meetingUrl
     * @param Carbon $scheduledAt
     *
     * @return string
     */
    public function createEvent(
        GoogleCredential $credential,
        string $studentName,
        string $certificationName,
        string $topic,
        string $meetingUrl,
        Carbon $scheduledAt,
    ): string {
        $client = $this->createAuthorizedClient($credential);
        $service = new Calendar($client);

        $event = new Event([
            'summary' => $studentName.' '.$certificationName,
            'description' => __('mentoring.google_calendar.event_description', [
                'topic' => $topic,
                'meeting_url' => $meetingUrl,
            ]),
            'location' => $meetingUrl,
            'start' => new EventDateTime([
                'dateTime' => $scheduledAt->toRfc3339String(),
                'timeZone' => config('app.timezone'),
            ]),
            'end' => new EventDateTime([
                'dateTime' => $scheduledAt->copy()->addHour()->toRfc3339String(),
                'timeZone' => config('app.timezone'),
            ]),
        ]);

        $createdEvent = $service->events->insert(
            $credential->calendar_id,
            $event,
        );

        return (string) $createdEvent->getId();
    }

    /**
     * Google Calendar の面談予定を削除する。
     *
     * @param GoogleCredential $credential
     * @param string $eventId
     *
     * @return void
     */
    public function deleteEvent(
        GoogleCredential $credential,
        string $eventId,
    ): void {
        $client = $this->createAuthorizedClient($credential);
        $service = new Calendar($client);

        $service->events->delete(
            $credential->calendar_id,
            $eventId,
        );
    }

    /**
     * Google Calendar 用の認証済みクライアントを生成する。
     *
     * @param GoogleCredential $credential
     *
     * @return GoogleClient
     */
    private function createAuthorizedClient(GoogleCredential $credential): GoogleClient
    {
        $client = $this->createClient();

        $expiresIn = max(
            0,
            now()->diffInSeconds($credential->token_expires_at, false)
        );

        $client->setAccessToken([
            'access_token' => $credential->access_token,
            'created' => now()->timestamp,
            'expires_in' => $expiresIn,
        ]);

        if ($client->isAccessTokenExpired()) {
            $token = $client->fetchAccessTokenWithRefreshToken(
                $credential->refresh_token
            );

            if (isset($token['access_token'])) {
                $credential->access_token = (string) $token['access_token'];
                $credential->token_expires_at = now()->addSeconds(
                    (int) ($token['expires_in'] ?? 3600)
                );
                $credential->save();
            }
        }

        return $client;
    }
}
