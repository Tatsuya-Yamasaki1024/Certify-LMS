<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Settings;

use App\Models\GoogleCredential;
use App\Models\User;
use App\Services\GoogleCalendarService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

/**
 * Google Calendar OAuth 連携の認証・認可・資格情報保存を検証する Feature テスト。
 */
class GoogleCalendarTest extends TestCase
{
    use RefreshDatabase;

    // 未認証ユーザーがGoogle Calendar連携画面にアクセスするとログイン画面へリダイレクトされることを確認する
    public function test_unauthenticated_request_is_redirected(): void
    {
        // Arrange

        // Act
        $response = $this->get(route('settings.google-calendar.redirect'));

        // Assert
        $response->assertRedirect(route('login'));
    }

    // 受講生がGoogle Calendar連携機能にアクセスすると403 Forbiddenになることを確認する
    public function test_student_is_forbidden_on_google_calendar_endpoint(): void
    {
        // Arrange
        $student = User::factory()->student()->create();

        // Act
        $response = $this->actingAs($student)
            ->get(route('settings.google-calendar.redirect'));

        // Assert
        $response->assertForbidden();
    }

    // 認証済みコーチのOAuthコールバックでGoogle Calendarの資格情報が保存されることを確認する
    public function test_callback_saves_google_credential_for_authenticated_coach(): void
    {
        // Arrange
        $coach = User::factory()->coach()->create();
        $state = Crypt::encryptString((string) $coach->id);

        $googleCalendarService = $this->mock(GoogleCalendarService::class);

        $googleCalendarService
            ->shouldReceive('exchangeCode')
            ->once()
            ->with('authorization-code')
            ->andReturn([
                'access_token' => 'access-token',
                'refresh_token' => 'refresh-token',
                'expires_in' => 3600,
            ]);

        $googleCalendarService
            ->shouldReceive('primaryCalendarId')
            ->once()
            ->with([
                'access_token' => 'access-token',
                'refresh_token' => 'refresh-token',
                'expires_in' => 3600,
            ])
            ->andReturn('primary');

        // Act
        $response = $this->actingAs($coach)->get(
            route('settings.google-calendar.callback', [
                'state' => $state,
                'code' => 'authorization-code',
            ])
        );

        // Assert
        $response->assertRedirect(route('settings.availability.index'));

        $credential = GoogleCredential::where('user_id', $coach->id)->firstOrFail();

        $this->assertSame('primary', $credential->calendar_id);
        $this->assertSame('access-token', $credential->access_token);
        $this->assertSame('refresh-token', $credential->refresh_token);
    }

    // stateに別のコーチのIDが含まれている場合、コールバックが400エラーになることを確認する
    public function test_callback_is_forbidden_when_state_belongs_to_another_coach(): void
    {
        // Arrange
        $coach = User::factory()->coach()->create();
        $otherCoach = User::factory()->coach()->create();
        $state = Crypt::encryptString((string) $otherCoach->id);

        // Act
        $response = $this->actingAs($coach)->get(
            route('settings.google-calendar.callback', [
                'state' => $state,
                'code' => 'authorization-code',
            ])
        );

        // Assert
        $response->assertStatus(400);
    }

    // stateが不正な場合、コールバックが400エラーになることを確認する
    public function test_callback_is_bad_request_when_state_is_invalid(): void
    {
        // Arrange
        $coach = User::factory()->coach()->create();

        // Act
        $response = $this->actingAs($coach)->get(
            route('settings.google-calendar.callback', [
                'state' => 'invalid-state',
                'code' => 'authorization-code',
            ])
        );

        // Assert
        $response->assertStatus(400);
    }

    // 認可コードが指定されていない場合、コールバックが400エラーになることを確認する
    public function test_callback_returns_bad_request_when_authorization_code_is_missing(): void
    {
        // Arrange
        $coach = User::factory()->coach()->create();
        $state = Crypt::encryptString((string) $coach->id);

        // Act
        $response = $this->actingAs($coach)->get(
            route('settings.google-calendar.callback', [
                'state' => $state,
            ])
        );

        // Assert
        $response->assertStatus(400);
    }

    // Google Calendarからアクセストークンが取得できない場合、コールバックが400エラーになることを確認する
    public function test_callback_returns_bad_request_when_access_token_is_missing(): void
    {
        // Arrange
        $coach = User::factory()->coach()->create();
        $state = Crypt::encryptString((string) $coach->id);

        $googleCalendarService = $this->mock(GoogleCalendarService::class);

        $googleCalendarService
            ->shouldReceive('exchangeCode')
            ->once()
            ->with('authorization-code')
            ->andReturn([
                'refresh_token' => 'refresh-token',
                'expires_in' => 3600,
            ]);

        // Act
        $response = $this->actingAs($coach)->get(
            route('settings.google-calendar.callback', [
                'state' => $state,
                'code' => 'authorization-code',
            ])
        );

        // Assert
        $response->assertStatus(400);
    }

    // Google Calendarを再連携した際、新しいrefresh_tokenが返されなくても既存のトークンが保持されることを確認する
    public function test_callback_preserves_existing_refresh_token_when_reconnecting(): void
    {
        // Arrange
        $coach = User::factory()->coach()->create();

        GoogleCredential::create([
            'user_id' => $coach->id,
            'calendar_id' => 'primary',
            'access_token' => 'old-access-token',
            'refresh_token' => 'existing-refresh-token',
            'token_expires_at' => now()->addHour(),
            'connected_at' => now(),
        ]);

        $state = Crypt::encryptString((string) $coach->id);

        $googleCalendarService = $this->mock(GoogleCalendarService::class);

        $googleCalendarService
            ->shouldReceive('exchangeCode')
            ->once()
            ->with('authorization-code')
            ->andReturn([
                'access_token' => 'new-access-token',
                'expires_in' => 3600,
            ]);

        $googleCalendarService
            ->shouldReceive('primaryCalendarId')
            ->once()
            ->with([
                'access_token' => 'new-access-token',
                'expires_in' => 3600,
            ])
            ->andReturn('primary');

        // Act
        $response = $this->actingAs($coach)->get(
            route('settings.google-calendar.callback', [
                'state' => $state,
                'code' => 'authorization-code',
            ])
        );

        // Assert
        $response->assertRedirect(route('settings.availability.index'));

        $credential = GoogleCredential::where('user_id', $coach->id)->firstOrFail();

        $this->assertSame('new-access-token', $credential->access_token);
        $this->assertSame('existing-refresh-token', $credential->refresh_token);
    }

    // Google Calendarの連携解除時に自身の認証情報が削除されることを確認する
    public function test_coach_can_disconnect_google_calendar(): void
    {
        // Arrange
        $coach = User::factory()->coach()->create();

        GoogleCredential::create([
            'user_id' => $coach->id,
            'calendar_id' => 'primary',
            'access_token' => 'access-token',
            'refresh_token' => 'refresh-token',
            'token_expires_at' => now()->addHour(),
            'connected_at' => now(),
        ]);

        // Act
        $response = $this->actingAs($coach)
            ->delete(route('settings.google-calendar.destroy'));

        // Assert
        $response->assertRedirect(route('settings.availability.index'));

        $this->assertDatabaseMissing('google_credentials', [
            'user_id' => $coach->id,
        ]);
    }

    // 受講生がGoogle Calendarの連携解除を実行できないことを確認する
    public function test_student_cannot_disconnect_google_calendar(): void
    {
        // Arrange
        $student = User::factory()->student()->create();

        // Act
        $response = $this->actingAs($student)
            ->delete(route('settings.google-calendar.destroy'));

        // Assert
        $response->assertForbidden();
    }
}
