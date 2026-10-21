<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PaymentStatus;
use App\Models\MeetingPack;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->student(),
            'meeting_pack_id' => MeetingPack::factory(),
            'status' => PaymentStatus::Pending,
            'amount' => 5000,
            'quantity' => 1,
            'stripe_checkout_session_id' => null,
            'stripe_payment_intent_id' => null,
            'paid_at' => null,
            'meeting_count' => fn (array $attributes) => MeetingPack::query()
                ->find($attributes['meeting_pack_id'])?->meeting_count ?? 1,
        ];
    }

    /**
     * 決済が成功した購入記録を作成する。
     */
    public function succeeded(): static
    {
        return $this->state(fn (): array => [
            'status' => PaymentStatus::Succeeded,
            'paid_at' => now(),
        ]);
    }

    /**
     * 決済に失敗した購入記録を作成する。
     */
    public function failed(): static
    {
        return $this->state(fn (): array => [
            'status' => PaymentStatus::Failed,
        ]);
    }
}
