<?php

declare(strict_types=1);

namespace Tests\Feature\Http\MeetingQuota;

use App\Enums\MeetingQuotaTransactionType;
use App\Enums\PaymentStatus;
use App\Models\MeetingPack;
use App\Models\MeetingQuotaTransaction;
use App\Models\Payment;
use App\Models\StripeWebhookEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class StripeWebhookTest extends TestCase
{
    use RefreshDatabase;

    private string $webhookSecret = 'whsec_test_secret';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.stripe.webhook_secret' => $this->webhookSecret,
        ]);
    }

    // 決済完了イベントを受信すると面談回数を加算する
    public function test_paid_checkout_session_grants_meeting_quota(): void
    {
        $meetingPack = MeetingPack::factory()
            ->withCount(5)
            ->create();

        $payment = Payment::factory()->create([
            'meeting_pack_id' => $meetingPack->id,
            'status' => PaymentStatus::Pending,
            'quantity' => 1,
            'stripe_checkout_session_id' => 'cs_test_paid',
        ]);

        $response = $this->postSignedWebhook(
            $this->checkoutCompletedEvent($payment, 'cs_test_paid'),
        );

        $response->assertOk();

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'status' => PaymentStatus::Succeeded->value,
            'stripe_checkout_session_id' => 'cs_test_paid',
            'stripe_payment_intent_id' => 'pi_test_paid',
        ]);

        $this->assertSame(
            1,
            MeetingQuotaTransaction::query()
                ->where('related_payment_id', $payment->id)
                ->where('type', MeetingQuotaTransactionType::Purchased)
                ->count(),
        );

        $this->assertDatabaseHas('meeting_quota_transactions', [
            'related_payment_id' => $payment->id,
            'type' => MeetingQuotaTransactionType::Purchased->value,
            'amount' => 5,
        ]);
    }

    // 同じ決済完了イベントを重複受信しても面談回数を二重加算しない
    public function test_duplicate_checkout_completed_event_does_not_grant_quota_twice(): void
    {
        $meetingPack = MeetingPack::factory()
            ->withCount(3)
            ->create();

        $payment = Payment::factory()->create([
            'meeting_pack_id' => $meetingPack->id,
            'status' => PaymentStatus::Pending,
            'stripe_checkout_session_id' => 'cs_test_duplicate',
        ]);

        $event = $this->checkoutCompletedEvent(
            $payment,
            'cs_test_duplicate',
        );

        $this->postSignedWebhook($event)->assertOk();
        $this->postSignedWebhook($event)->assertOk();

        $this->assertSame(
            1,
            MeetingQuotaTransaction::query()
                ->where('related_payment_id', $payment->id)
                ->where('type', MeetingQuotaTransactionType::Purchased)
                ->count(),
        );

        $this->assertDatabaseHas('meeting_quota_transactions', [
            'related_payment_id' => $payment->id,
            'amount' => 3,
        ]);
    }

    // Checkout Session の期限切れイベントで決済を失敗扱いにする
    public function test_expired_checkout_session_marks_pending_payment_failed(): void
    {
        $payment = Payment::factory()->create([
            'status' => PaymentStatus::Pending,
            'stripe_checkout_session_id' => 'cs_test_expired',
        ]);

        $response = $this->postSignedWebhook([
            'id' => 'evt_test_expired',
            'type' => 'checkout.session.expired',
            'data' => [
                'object' => [
                    'id' => 'cs_test_expired',
                    'object' => 'checkout.session',
                    'metadata' => [
                        'payment_id' => $payment->id,
                    ],
                ],
            ],
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'status' => PaymentStatus::Failed->value,
        ]);
    }

    // 決済成功後に失敗イベントを受信しても成功状態を維持する
    public function test_late_expiration_event_does_not_overwrite_succeeded_payment(): void
    {
        $payment = Payment::factory()->succeeded()->create([
            'stripe_checkout_session_id' => 'cs_test_already_paid',
        ]);

        $response = $this->postSignedWebhook([
            'id' => 'evt_test_late_expiration',
            'type' => 'checkout.session.expired',
            'data' => [
                'object' => [
                    'id' => 'cs_test_already_paid',
                    'object' => 'checkout.session',
                    'metadata' => [
                        'payment_id' => $payment->id,
                    ],
                ],
            ],
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'status' => PaymentStatus::Succeeded->value,
        ]);
    }

    // PaymentIntent の決済失敗イベントで決済を失敗扱いにする
    public function test_payment_intent_failure_marks_pending_payment_failed(): void
    {
        $payment = Payment::factory()->create([
            'status' => PaymentStatus::Pending,
        ]);

        $response = $this->postSignedWebhook([
            'id' => 'evt_test_payment_failed',
            'type' => 'payment_intent.payment_failed',
            'data' => [
                'object' => [
                    'id' => 'pi_test_failed',
                    'object' => 'payment_intent',
                    'metadata' => [
                        'payment_id' => $payment->id,
                    ],
                ],
            ],
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'status' => PaymentStatus::Failed->value,
        ]);
    }

    // 決済未確定のCheckout Sessionでは面談回数を加算しない
    public function test_unpaid_checkout_session_does_not_grant_meeting_quota(): void
    {
        $meetingPack = MeetingPack::factory()
            ->withCount(5)
            ->create();

        $payment = Payment::factory()->create([
            'meeting_pack_id' => $meetingPack->id,
            'status' => PaymentStatus::Pending,
            'stripe_checkout_session_id' => 'cs_test_unpaid',
        ]);

        $response = $this->postSignedWebhook([
            'id' => 'evt_test_unpaid',
            'type' => 'checkout.session.completed',
            'data' => [
                'object' => [
                    'id' => 'cs_test_unpaid',
                    'object' => 'checkout.session',
                    'payment_status' => 'unpaid',
                    'payment_intent' => 'pi_test_unpaid',
                    'metadata' => [
                        'payment_id' => $payment->id,
                    ],
                ],
            ],
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'status' => PaymentStatus::Pending->value,
        ]);

        $this->assertDatabaseMissing('meeting_quota_transactions', [
            'related_payment_id' => $payment->id,
            'type' => MeetingQuotaTransactionType::Purchased->value,
        ]);
    }

    // 決済完了イベントを正常処理するとイベント記録がprocessedになる
    public function test_successfully_processed_event_is_recorded(): void
    {
        $meetingPack = MeetingPack::factory()
            ->withCount(5)
            ->create();

        $payment = Payment::factory()->create([
            'meeting_pack_id' => $meetingPack->id,
            'status' => PaymentStatus::Pending,
            'stripe_checkout_session_id' => 'cs_test_recorded',
        ]);

        $event = $this->checkoutCompletedEvent(
            $payment,
            'cs_test_recorded',
        );

        $this->postSignedWebhook($event)->assertOk();

        $this->assertDatabaseHas('stripe_webhook_events', [
            'stripe_event_id' => 'evt_test_completed',
            'event_type' => 'checkout.session.completed',
            'status' => 'processed',
        ]);

        $this->assertNotNull(
            StripeWebhookEvent::query()
                ->where('stripe_event_id', 'evt_test_completed')
                ->value('processed_at'),
        );
    }

    // 同じイベントを再送してもイベント記録は1件だけ保持する
    public function test_duplicate_event_is_recorded_only_once(): void
    {
        $meetingPack = MeetingPack::factory()
            ->withCount(3)
            ->create();

        $payment = Payment::factory()->create([
            'meeting_pack_id' => $meetingPack->id,
            'status' => PaymentStatus::Pending,
            'stripe_checkout_session_id' => 'cs_test_event_duplicate',
        ]);

        $event = $this->checkoutCompletedEvent(
            $payment,
            'cs_test_event_duplicate',
        );

        $this->postSignedWebhook($event)->assertOk();
        $this->postSignedWebhook($event)->assertOk();

        $this->assertDatabaseCount('stripe_webhook_events', 1);

        $this->assertSame(
            1,
            MeetingQuotaTransaction::query()
                ->where('related_payment_id', $payment->id)
                ->where('type', MeetingQuotaTransactionType::Purchased)
                ->count(),
        );
    }

    // 対応するPaymentが存在しない決済完了イベントを失敗として記録する
    public function test_missing_payment_records_event_as_failed(): void
    {
        $event = [
            'id' => 'evt_test_missing_payment',
            'type' => 'checkout.session.completed',
            'data' => [
                'object' => [
                    'id' => 'cs_test_missing_payment',
                    'object' => 'checkout.session',
                    'payment_status' => 'paid',
                    'payment_intent' => 'pi_test_missing_payment',
                    'client_reference_id' => '01AAAAAAAAAAAAAAAAAAAAAAAA',
                    'metadata' => [
                        'payment_id' => '01AAAAAAAAAAAAAAAAAAAAAAAA',
                    ],
                ],
            ],
        ];

        $this->postSignedWebhook($event)->assertStatus(500);

        $this->assertDatabaseHas('stripe_webhook_events', [
            'stripe_event_id' => 'evt_test_missing_payment',
            'event_type' => 'checkout.session.completed',
            'status' => 'failed',
        ]);

        $this->assertDatabaseCount('meeting_quota_transactions', 0);
    }

    // 不正な署名のWebhookを拒否する
    public function test_webhook_rejects_invalid_signature(): void
    {
        $response = $this->call(
            'POST',
            route('stripe.webhook'),
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_STRIPE_SIGNATURE' => 't=123,v1=invalid',
            ],
            json_encode([
                'id' => 'evt_test_invalid',
                'type' => 'checkout.session.completed',
                'data' => [
                    'object' => [],
                ],
            ], JSON_THROW_ON_ERROR),
        );

        $response->assertStatus(400);
        $this->assertDatabaseCount('meeting_quota_transactions', 0);
    }

    /**
     * 決済完了イベントのテストデータを作成する。
     *
     * @return array<string, mixed>
     */
    private function checkoutCompletedEvent(
        Payment $payment,
        string $sessionId,
    ): array {
        return [
            'id' => 'evt_test_completed',
            'type' => 'checkout.session.completed',
            'data' => [
                'object' => [
                    'id' => $sessionId,
                    'object' => 'checkout.session',
                    'payment_status' => 'paid',
                    'payment_intent' => 'pi_test_paid',
                    'client_reference_id' => $payment->id,
                    'metadata' => [
                        'payment_id' => $payment->id,
                    ],
                ],
            ],
        ];
    }

    /**
     * Stripe形式の署名を付けてWebhookを送信する。
     *
     * @param array<string, mixed> $event
     */
    private function postSignedWebhook(array $event): TestResponse
    {
        $payload = json_encode($event, JSON_THROW_ON_ERROR);
        $timestamp = time();
        $signature = hash_hmac(
            'sha256',
            $timestamp.'.'.$payload,
            $this->webhookSecret,
        );

        return $this->call(
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
        );
    }
}
