<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\MeetingReminderType;
use App\Models\Meeting;
use App\Models\MeetingReminderDelivery;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MeetingReminderDelivery>
 */
class MeetingReminderDeliveryFactory extends Factory
{
    protected $model = MeetingReminderDelivery::class;

    /**
     * リマインダー送信履歴のデフォルト値を定義する。
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'meeting_id' => Meeting::factory(),
            'user_id' => User::factory(),
            'reminder_type' => MeetingReminderType::Eve,
        ];
    }
}
