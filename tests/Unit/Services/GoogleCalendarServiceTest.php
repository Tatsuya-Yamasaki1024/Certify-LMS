<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\GoogleCredential;
use App\Models\User;
use App\Services\GoogleCalendarService;
use Carbon\Carbon;
use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Google Calendar サービスのトークン更新処理を検証する。
 */
class GoogleCalendarServiceTest extends TestCase
{
    use RefreshDatabase;

    // アクセストークンの期限切れ時にトークンを更新してDBに保存する
    public function test_refreshes_expired_access_token_and_saves_new_token(): void
    {
        // Arrange
        config([
            'services.google.client_id' => 'test-client-id',
            'services.google.client_secret' => 'test-client-secret',
            'services.google.redirect_uri' => 'http://localhost/callback',
        ]);

        $coach = User::factory()->coach()->create();

        $credential = GoogleCredential::create([
            'user_id' => $coach->id,
            'calendar_id' => 'primary',
            'access_token' => 'expired-access-token',
            'refresh_token' => 'refresh-token',
            'token_expires_at' => now()->subMinute(),
            'connected_at' => now()->subDay(),
        ]);

        $handler = HandlerStack::create(new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], json_encode([
                'access_token' => 'new-access-token',
                'expires_in' => 3600,
                'token_type' => 'Bearer',
            ], JSON_THROW_ON_ERROR)),
            new Response(200, ['Content-Type' => 'application/json'], json_encode([
                'calendars' => [
                    'primary' => [
                        'busy' => [],
                    ],
                ],
                'timeMin' => now()->toRfc3339String(),
                'timeMax' => now()->addDay()->toRfc3339String(),
            ], JSON_THROW_ON_ERROR)),
        ]));

        $service = new GoogleCalendarService;
        $service->setHttpClient(new HttpClient(['handler' => $handler]));

        // Act
        $service->busyIntervals(
            $credential,
            now(),
            now()->addDay(),
        );

        // Assert
        $credential->refresh();

        $this->assertSame('new-access-token', $credential->access_token);
        $this->assertTrue($credential->token_expires_at->isFuture());
    }

    // リフレッシュトークンによる更新に失敗した場合に例外が発生する
    public function test_throws_exception_when_refresh_token_request_fails(): void
    {
        // Arrange
        config([
            'services.google.client_id' => 'test-client-id',
            'services.google.client_secret' => 'test-client-secret',
            'services.google.redirect_uri' => 'http://localhost/callback',
        ]);

        $coach = User::factory()->coach()->create();

        $credential = GoogleCredential::create([
            'user_id' => $coach->id,
            'calendar_id' => 'primary',
            'access_token' => 'expired-access-token',
            'refresh_token' => 'refresh-token',
            'token_expires_at' => now()->subMinute(),
            'connected_at' => now()->subDay(),
        ]);

        $handler = HandlerStack::create(new MockHandler([
            new Response(400, ['Content-Type' => 'application/json'], json_encode([
                'error' => 'invalid_grant',
                'error_description' => 'Token has been expired or revoked.',
            ], JSON_THROW_ON_ERROR)),
        ]));

        $service = new GoogleCalendarService;
        $service->setHttpClient(new HttpClient(['handler' => $handler]));

        // Act / Assert
        $this->expectException(\Exception::class);

        $service->busyIntervals(
            $credential,
            now(),
            now()->addDay(),
        );
    }

    // トークン更新に失敗した場合に既存のアクセストークンを保持する
    public function test_preserves_existing_access_token_when_refresh_fails(): void
    {
        // Arrange
        config([
            'services.google.client_id' => 'test-client-id',
            'services.google.client_secret' => 'test-client-secret',
            'services.google.redirect_uri' => 'http://localhost/callback',
        ]);

        $coach = User::factory()->coach()->create();

        $credential = GoogleCredential::create([
            'user_id' => $coach->id,
            'calendar_id' => 'primary',
            'access_token' => 'expired-access-token',
            'refresh_token' => 'refresh-token',
            'token_expires_at' => now()->subMinute(),
            'connected_at' => now()->subDay(),
        ]);

        $handler = HandlerStack::create(new MockHandler([
            new Response(
                400,
                ['Content-Type' => 'application/json'],
                json_encode([
                    'error' => 'invalid_grant',
                    'error_description' => 'Token has been expired or revoked.',
                ], JSON_THROW_ON_ERROR),
            ),
        ]));

        $service = new GoogleCalendarService;
        $service->setHttpClient(new HttpClient(['handler' => $handler]));

        // Act
        $exception = null;

        try {
            $service->busyIntervals(
                $credential,
                now(),
                now()->addDay(),
            );
        } catch (\Throwable $e) {
            $exception = $e;
        }

        // Assert
        $this->assertNotNull($exception);

        $credential->refresh();

        $this->assertSame('expired-access-token', $credential->access_token);
    }

    // Busy時間帯取得時に指定期間とAsia/Tokyoのタイムゾーンを使用する
    public function test_requests_busy_intervals_with_configured_timezone_and_period(): void
    {
        // Arrange
        config([
            'services.google.client_id' => 'test-client-id',
            'services.google.client_secret' => 'test-client-secret',
            'services.google.redirect_uri' => 'http://localhost/callback',
            'app.timezone' => 'Asia/Tokyo',
        ]);

        $coach = User::factory()->coach()->create();

        $start = Carbon::parse('2026-10-12 10:00:00', 'Asia/Tokyo');
        $end = Carbon::parse('2026-10-12 11:00:00', 'Asia/Tokyo');

        $credential = GoogleCredential::create([
            'user_id' => $coach->id,
            'calendar_id' => 'primary',
            'access_token' => 'valid-access-token',
            'refresh_token' => 'refresh-token',
            'token_expires_at' => now()->addHour(),
            'connected_at' => now(),
        ]);

        $history = [];

        $handler = HandlerStack::create(new MockHandler([
            new Response(
                200,
                ['Content-Type' => 'application/json'],
                json_encode([
                    'calendars' => [
                        'primary' => [
                            'busy' => [],
                        ],
                    ],
                    'timeMin' => $start->toRfc3339String(),
                    'timeMax' => $end->toRfc3339String(),
                ], JSON_THROW_ON_ERROR),
            ),
        ]));

        $handler->push(Middleware::history($history));

        $service = new GoogleCalendarService;
        $service->setHttpClient(new HttpClient(['handler' => $handler]));

        // Act
        $result = $service->busyIntervals($credential, $start, $end);

        // Assert
        $this->assertSame([], $result);
        $this->assertCount(1, $history);

        $request = $history[0]['request'];

        $requestBody = json_decode(
            (string) $request->getBody(),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        $this->assertSame(
            $start->toRfc3339String(),
            $requestBody['timeMin'],
        );

        $this->assertSame(
            $end->toRfc3339String(),
            $requestBody['timeMax'],
        );

        $this->assertSame('Asia/Tokyo', $requestBody['timeZone']);
        $this->assertSame(
            [['id' => 'primary']],
            $requestBody['items'],
        );
    }

    // 面談予定の登録時にタイトル・説明・場所・日時・タイムゾーンを正しく設定する
    public function test_creates_event_with_correct_details_and_timezone(): void
    {
        // Arrange
        config([
            'services.google.client_id' => 'test-client-id',
            'services.google.client_secret' => 'test-client-secret',
            'services.google.redirect_uri' => 'http://localhost/callback',
            'app.timezone' => 'Asia/Tokyo',
        ]);

        $coach = User::factory()->coach()->create();

        $credential = GoogleCredential::create([
            'user_id' => $coach->id,
            'calendar_id' => 'primary',
            'access_token' => 'valid-access-token',
            'refresh_token' => 'refresh-token',
            'token_expires_at' => now()->addHour(),
            'connected_at' => now(),
        ]);

        $scheduledAt = Carbon::parse(
            '2026-10-12 10:00:00',
            'Asia/Tokyo',
        );

        $history = [];

        $handler = HandlerStack::create(new MockHandler([
            new Response(
                200,
                ['Content-Type' => 'application/json'],
                json_encode([
                    'id' => 'google-event-123',
                ], JSON_THROW_ON_ERROR),
            ),
        ]));

        $handler->push(Middleware::history($history));

        $service = new GoogleCalendarService;
        $service->setHttpClient(new HttpClient(['handler' => $handler]));

        // Act
        $eventId = $service->createEvent(
            $credential,
            '山田太郎',
            'Laravel認定',
            '模試の学習方法について',
            'https://example.com/meeting',
            $scheduledAt,
        );

        // Assert
        $this->assertSame('google-event-123', $eventId);
        $this->assertCount(1, $history);

        $request = $history[0]['request'];

        $this->assertSame('POST', $request->getMethod());
        $this->assertStringContainsString(
            '/calendars/primary/events',
            $request->getUri()->getPath(),
        );

        $body = json_decode(
            (string) $request->getBody(),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        $this->assertSame('山田太郎 Laravel認定', $body['summary']);
        $this->assertSame(
            "話題：模試の学習方法について\n面談URL：https://example.com/meeting",
            $body['description'],
        );
        $this->assertSame(
            'https://example.com/meeting',
            $body['location'],
        );

        $this->assertSame(
            $scheduledAt->toRfc3339String(),
            $body['start']['dateTime'],
        );
        $this->assertSame(
            'Asia/Tokyo',
            $body['start']['timeZone'],
        );

        $this->assertSame(
            $scheduledAt->copy()->addHour()->toRfc3339String(),
            $body['end']['dateTime'],
        );
        $this->assertSame(
            'Asia/Tokyo',
            $body['end']['timeZone'],
        );
    }

    // 面談予定の削除時に指定したカレンダーIDとイベントIDを使用する
    public function test_deletes_event_from_specified_calendar(): void
    {
        // Arrange
        config([
            'services.google.client_id' => 'test-client-id',
            'services.google.client_secret' => 'test-client-secret',
            'services.google.redirect_uri' => 'http://localhost/callback',
        ]);

        $coach = User::factory()->coach()->create();

        $credential = GoogleCredential::create([
            'user_id' => $coach->id,
            'calendar_id' => 'primary',
            'access_token' => 'valid-access-token',
            'refresh_token' => 'refresh-token',
            'token_expires_at' => now()->addHour(),
            'connected_at' => now(),
        ]);

        $history = [];

        $handler = HandlerStack::create(new MockHandler([
            new Response(204),
        ]));

        $handler->push(Middleware::history($history));

        $service = new GoogleCalendarService;
        $service->setHttpClient(new HttpClient(['handler' => $handler]));

        // Act
        $service->deleteEvent($credential, 'google-event-123');

        // Assert
        $this->assertCount(1, $history);

        $request = $history[0]['request'];

        $this->assertSame('DELETE', $request->getMethod());
        $this->assertSame(
            '/calendar/v3/calendars/primary/events/google-event-123',
            $request->getUri()->getPath(),
        );
    }

    // Google Calendarから取得したBusy時間帯を正しく解析する
    public function test_parses_busy_intervals_including_all_day_period(): void
    {
        // Arrange
        config([
            'services.google.client_id' => 'test-client-id',
            'services.google.client_secret' => 'test-client-secret',
            'services.google.redirect_uri' => 'http://localhost/callback',
            'app.timezone' => 'Asia/Tokyo',
        ]);

        $coach = User::factory()->coach()->create();

        $credential = GoogleCredential::create([
            'user_id' => $coach->id,
            'calendar_id' => 'primary',
            'access_token' => 'valid-access-token',
            'refresh_token' => 'refresh-token',
            'token_expires_at' => now()->addHour(),
            'connected_at' => now(),
        ]);

        $handler = HandlerStack::create(new MockHandler([
            new Response(
                200,
                ['Content-Type' => 'application/json'],
                json_encode([
                    'calendars' => [
                        'primary' => [
                            'busy' => [
                                [
                                    'start' => '2026-10-12T00:00:00+09:00',
                                    'end' => '2026-10-13T00:00:00+09:00',
                                ],
                            ],
                        ],
                    ],
                ], JSON_THROW_ON_ERROR),
            ),
        ]));

        $service = new GoogleCalendarService;
        $service->setHttpClient(new HttpClient(['handler' => $handler]));

        $start = Carbon::parse(
            '2026-10-12 00:00:00',
            'Asia/Tokyo',
        );

        $end = Carbon::parse(
            '2026-10-13 00:00:00',
            'Asia/Tokyo',
        );

        // Act
        $intervals = $service->busyIntervals(
            $credential,
            $start,
            $end,
        );

        // Assert
        $this->assertCount(1, $intervals);

        $this->assertSame(
            '2026-10-12T00:00:00+09:00',
            $intervals[0]['start']->toIso8601String(),
        );

        $this->assertSame(
            '2026-10-13T00:00:00+09:00',
            $intervals[0]['end']->toIso8601String(),
        );
    }
}
