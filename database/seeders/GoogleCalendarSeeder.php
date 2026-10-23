<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\GoogleCredential;
use App\Models\User;
use Illuminate\Database\Seeder;

class GoogleCalendarSeeder extends Seeder
{
    /**
     * Google Calendar 連携済みコーチのデモデータを生成する。
     */
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        $coach = User::query()
            ->where('email', 'coach@certify-lms.test')
            ->first();

        if ($coach === null) {
            return;
        }

        GoogleCredential::query()->firstOrCreate(
            ['user_id' => $coach->id],
            [
                'calendar_id' => 'primary',
                'access_token' => 'dummy-access-token',
                'refresh_token' => 'dummy-refresh-token',
                'token_expires_at' => now()->addHour(),
                'connected_at' => now(),
            ]
        );
    }
}
