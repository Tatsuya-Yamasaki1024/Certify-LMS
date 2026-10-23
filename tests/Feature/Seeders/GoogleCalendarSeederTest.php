<?php

declare(strict_types=1);

namespace Tests\Feature\Seeders;

use App\Models\GoogleCredential;
use App\Models\User;
use Database\Seeders\GoogleCalendarSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GoogleCalendarSeederTest extends TestCase
{
    use RefreshDatabase;

    // 固定コーチにダミーのGoogle認証情報が作成される
    public function test_creates_dummy_credentials_for_primary_coach(): void
    {
        $coach = User::factory()->coach()->create([
            'email' => 'coach@certify-lms.test',
        ]);

        $this->seed(GoogleCalendarSeeder::class);

        $credential = GoogleCredential::query()
            ->where('user_id', $coach->id)
            ->first();

        $this->assertNotNull($credential);
        $this->assertSame('primary', $credential->calendar_id);
        $this->assertSame('dummy-access-token', $credential->access_token);
        $this->assertSame('dummy-refresh-token', $credential->refresh_token);
    }

    // 未連携コーチにはGoogle認証情報が作成されない
    public function test_does_not_create_credentials_for_secondary_coach(): void
    {
        $coach = User::factory()->coach()->create([
            'email' => 'coach2@certify-lms.test',
        ]);

        $this->seed(GoogleCalendarSeeder::class);

        $this->assertDatabaseMissing('google_credentials', [
            'user_id' => $coach->id,
        ]);
    }

    // Seederを再実行してもGoogle認証情報が重複しない
    public function test_does_not_duplicate_credentials_when_reseeded(): void
    {
        User::factory()->coach()->create([
            'email' => 'coach@certify-lms.test',
        ]);

        $this->seed(GoogleCalendarSeeder::class);
        $this->seed(GoogleCalendarSeeder::class);

        $this->assertDatabaseCount('google_credentials', 1);
    }

    // 既存のGoogle認証情報をSeederで上書きしない
    public function test_does_not_overwrite_existing_credentials(): void
    {
        $coach = User::factory()->coach()->create([
            'email' => 'coach@certify-lms.test',
        ]);

        GoogleCredential::query()->create([
            'user_id' => $coach->id,
            'calendar_id' => 'primary',
            'access_token' => 'real-access-token',
            'refresh_token' => 'real-refresh-token',
            'token_expires_at' => now()->addDays(7),
            'connected_at' => now()->subDay(),
        ]);

        $this->seed(GoogleCalendarSeeder::class);

        $credential = GoogleCredential::query()
            ->where('user_id', $coach->id)
            ->firstOrFail();

        $this->assertSame('real-access-token', $credential->access_token);
        $this->assertSame('real-refresh-token', $credential->refresh_token);
        $this->assertDatabaseCount('google_credentials', 1);
    }

    // 本番環境ではダミーのGoogle認証情報を作成しない
    public function test_does_not_seed_dummy_credentials_in_production(): void
    {
        $coach = User::factory()->coach()->create([
            'email' => 'coach@certify-lms.test',
        ]);

        $originalEnvironment = app()->environment();

        try {
            app()->detectEnvironment(fn () => 'production');

            (new GoogleCalendarSeeder)->run();

            $this->assertDatabaseMissing('google_credentials', [
                'user_id' => $coach->id,
            ]);
        } finally {
            app()->detectEnvironment(fn () => $originalEnvironment);
        }
    }
}
