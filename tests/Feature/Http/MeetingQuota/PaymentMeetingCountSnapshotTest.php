<?php

declare(strict_types=1);

namespace Tests\Feature\Http\MeetingQuota;

use App\Enums\MeetingQuotaTransactionType;
use App\Enums\PaymentStatus;
use App\Models\MeetingPack;
use App\Models\MeetingQuotaTransaction;
use App\Models\Payment;
use App\Models\User;
use App\Services\StripeCheckoutSessionCreator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Stripe\Checkout\Session;
use Tests\TestCase;

class PaymentMeetingCountSnapshotTest extends TestCase
{
    use RefreshDatabase;

    // 購入開始時に面談パックの回数を決済情報へ保存する
    public function test_checkout_saves_meeting_count_snapshot(): void
    {
        $student = User::factory()->student()->create();

        $meetingPack = MeetingPack::factory()
            ->published()
            ->withCount(5)
            ->withPrice(12000)
            ->create();

        $this->mock(
            StripeCheckoutSessionCreator::class,
            function (MockInterface $mock): void {
                $mock->shouldReceive('create')
                    ->once()
                    ->andReturn(Session::constructFrom([
                        'id' => 'cs_test_snapshot',
                        'url' => 'https://checkout.stripe.com/c/pay/cs_test_snapshot',
                    ]));
            },
        );

        $this->actingAs($student)->post(
            route('meeting-quota.checkout.create'),
            ['meeting_pack_id' => $meetingPack->id],
        )->assertRedirect(
            'https://checkout.stripe.com/c/pay/cs_test_snapshot',
        );

        $this->assertDatabaseHas('payments', [
            'user_id' => $student->id,
            'meeting_pack_id' => $meetingPack->id,
            'amount' => 12000,
            'quantity' => 1,
            'meeting_count' => 5,
            'status' => PaymentStatus::Pending->value,
        ]);
    }

    // 購入後に面談パックの回数が変更されても購入時点の回数を付与する
    public function test_webhook_grants_original_meeting_count_after_pack_changes(): void
    {
        config([
            'services.stripe.webhook_secret' => 'whsec_test_secret',
        ]);

        $student = User::factory()->student()->create();

        $meetingPack = MeetingPack::factory()
            ->published()
            ->withCount(5)
            ->create();

        $payment = Payment::factory()->create([
            'user_id' => $student->id,
            'meeting_pack_id' => $meetingPack->id,
            'status' => PaymentStatus::Pending,
            'quantity' => 1,
            'meeting_count' => 5,
            'stripe_checkout_session_id' => 'cs_test_snapshot_webhook',
        ]);

        // 購入後に管理者が面談パックの回数を変更した状況を再現
        $meetingPack->update([
            'meeting_count' => 10,
        ]);

        $event = [
            'id' => 'evt_test_snapshot_webhook',
            'type' => 'checkout.session.completed',
            'data' => [
                'object' => [
                    'id' => 'cs_test_snapshot_webhook',
                    'object' => 'checkout.session',
                    'payment_status' => 'paid',
                    'payment_intent' => 'pi_test_snapshot_webhook',
                    'client_reference_id' => $payment->id,
                    'metadata' => [
                        'payment_id' => $payment->id,
                    ],
                ],
            ],
        ];

        $payload = json_encode($event, JSON_THROW_ON_ERROR);
        $timestamp = time();

        $signature = hash_hmac(
            'sha256',
            $timestamp.'.'.$payload,
            'whsec_test_secret',
        );

        $this->call(
            'POST',
            route('stripe.webhook'),
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_STRIPE_SIGNATURE' => "t={$timestamp},v1={$signature}",
            ],
            $payload,
        )->assertOk();

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'status' => PaymentStatus::Succeeded->value,
            'meeting_count' => 5,
        ]);

        $this->assertDatabaseHas('meeting_quota_transactions', [
            'user_id' => $student->id,
            'related_payment_id' => $payment->id,
            'type' => MeetingQuotaTransactionType::Purchased->value,
            'amount' => 5,
        ]);

        $this->assertSame(
            1,
            MeetingQuotaTransaction::query()
                ->where('related_payment_id', $payment->id)
                ->where('type', MeetingQuotaTransactionType::Purchased)
                ->count(),
        );
    }
}
