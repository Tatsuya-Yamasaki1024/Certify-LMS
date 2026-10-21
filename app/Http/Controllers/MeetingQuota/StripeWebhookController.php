<?php

declare(strict_types=1);

namespace App\Http\Controllers\MeetingQuota;

use App\Enums\MeetingQuotaTransactionType;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\MeetingQuotaTransaction;
use App\Models\Payment;
use App\Models\StripeWebhookEvent;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;
use Throwable;
use UnexpectedValueException;

/**
 * Stripe Webhook を受信し、決済状態と面談回数を更新する。
 */
final class StripeWebhookController extends Controller
{
    /**
     * Stripe イベントを検証して処理する。
     */
    public function __invoke(Request $request): Response
    {
        $secret = config('services.stripe.webhook_secret');
        $signature = $request->header('Stripe-Signature');

        if (! is_string($secret) || $secret === '' || ! is_string($signature)) {
            return response('Invalid webhook configuration or signature.', 400);
        }

        try {
            $event = Webhook::constructEvent(
                $request->getContent(),
                $signature,
                $secret,
            );
        } catch (UnexpectedValueException|SignatureVerificationException $exception) {
            Log::warning('Stripe webhook signature or payload validation failed.', [
                'message' => $exception->getMessage(),
            ]);

            return response('Invalid webhook payload or signature.', 400);
        }

        if (
            ! isset($event->id)
            || ! is_string($event->id)
            || $event->id === ''
            || ! isset($event->type)
            || ! is_string($event->type)
        ) {
            return response('Invalid Stripe event.', 400);
        }

        try {
            DB::transaction(function () use ($event): void {
                $now = now();

                DB::table('stripe_webhook_events')->insertOrIgnore([
                    'stripe_event_id' => $event->id,
                    'event_type' => $event->type,
                    'status' => 'processing',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                $webhookEvent = StripeWebhookEvent::query()
                    ->where('stripe_event_id', $event->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($webhookEvent->status === 'processed') {
                    return;
                }

                $webhookEvent->update([
                    'status' => 'processing',
                    'error_message' => null,
                    'processed_at' => null,
                ]);

                match ($event->type) {
                    'checkout.session.completed' => $this->completeCheckout(
                        $event->data->object,
                    ),
                    'checkout.session.expired' => $this->failCheckout(
                        $event->data->object,
                    ),
                    'payment_intent.payment_failed' => $this->failPaymentIntent(
                        $event->data->object,
                    ),
                    default => null,
                };

                $webhookEvent->update([
                    'status' => 'processed',
                    'processed_at' => now(),
                ]);
            });
        } catch (Throwable $exception) {
            /*
             * 決済処理とイベント処理のトランザクションが失敗した場合、
             * 失敗記録を別トランザクションで保存する。
             *
             * 失敗記録を決済処理と同じトランザクションにすると、
             * ロールバックによって失敗記録まで消えてしまうため。
             */
            try {
                StripeWebhookEvent::query()->updateOrCreate(
                    ['stripe_event_id' => $event->id],
                    [
                        'event_type' => $event->type,
                        'status' => 'failed',
                        'error_message' => mb_substr(
                            $exception->getMessage(),
                            0,
                            5000,
                        ),
                        'processed_at' => null,
                    ],
                );
            } catch (Throwable $recordException) {
                Log::critical('Stripe webhook failure record could not be saved.', [
                    'event_id' => $event->id,
                    'event_type' => $event->type,
                    'exception' => $recordException,
                ]);
            }

            Log::error('Stripe webhook processing failed.', [
                'event_id' => $event->id,
                'event_type' => $event->type,
                'exception' => $exception,
            ]);

            return response('Webhook processing failed.', 500);
        }

        return response('Webhook received.', 200);
    }

    /**
     * Checkout Session の決済確定時に面談回数を加算する。
     */
    private function completeCheckout(object $session): void
    {
        if (($session->payment_status ?? null) !== 'paid') {
            return;
        }

        $sessionId = $session->id ?? null;
        $paymentId = $session->metadata->payment_id
            ?? $session->client_reference_id
            ?? null;

        if (! is_string($sessionId) || $sessionId === '') {
            throw new \RuntimeException(
                'Paid Checkout Session has no valid session ID.',
            );
        }

        if (! is_string($paymentId) || $paymentId === '') {
            throw new \RuntimeException(
                'Paid Checkout Session has no payment ID.',
            );
        }

        $payment = Payment::query()
            ->whereKey($paymentId)
            ->lockForUpdate()
            ->first();

        if ($payment === null) {
            throw new \RuntimeException(
                "Payment not found for Checkout Session: {$sessionId}",
            );
        }

        if (
            $payment->stripe_checkout_session_id !== null
            && $payment->stripe_checkout_session_id !== $sessionId
        ) {
            throw new \RuntimeException(
                "Checkout Session ID mismatch for Payment: {$payment->id}",
            );
        }

        if ($payment->status === PaymentStatus::Succeeded) {
            /*
             * すでに決済成功済みなら、回数の再付与は行わない。
             * 過去の処理で決済だけ成功し、付与が漏れている場合は
             * 別途整合性確認・復旧処理の対象とする。
             */
            return;
        }

        if ($payment->status !== PaymentStatus::Pending) {
            throw new \RuntimeException(
                "Payment is not pending: {$payment->id}",
            );
        }

        $meetingPack = $payment->meetingPack()->first();

        if ($meetingPack === null) {
            throw new \RuntimeException(
                "Meeting pack not found for Payment: {$payment->id}",
            );
        }

        $paymentIntentId = $session->payment_intent ?? null;

        $payment->update([
            'status' => PaymentStatus::Succeeded,
            'stripe_checkout_session_id' => $sessionId,
            'stripe_payment_intent_id' => is_string($paymentIntentId)
                ? $paymentIntentId
                : null,
            'paid_at' => now(),
        ]);

        $alreadyGranted = MeetingQuotaTransaction::query()
            ->where('related_payment_id', $payment->id)
            ->where('type', MeetingQuotaTransactionType::Purchased)
            ->exists();

        if ($alreadyGranted) {
            return;
        }

        MeetingQuotaTransaction::query()->create([
            'user_id' => $payment->user_id,
            'type' => MeetingQuotaTransactionType::Purchased,
            'amount' => $payment->meeting_count * $payment->quantity,
            'related_payment_id' => $payment->id,
            'note' => "面談パック購入: {$meetingPack->name}",
            'occurred_at' => now(),
        ]);
    }

    /**
     * Checkout Session の期限切れ時に決済を失敗扱いにする。
     */
    private function failCheckout(object $session): void
    {
        $sessionId = $session->id ?? null;
        $paymentId = $session->metadata->payment_id
            ?? $session->client_reference_id
            ?? null;

        $this->markPendingPaymentFailed(
            is_string($sessionId) ? $sessionId : null,
            is_string($paymentId) ? $paymentId : null,
        );
    }

    /**
     * PaymentIntent の決済失敗時に決済を失敗扱いにする。
     */
    private function failPaymentIntent(object $intent): void
    {
        $paymentId = $intent->metadata->payment_id ?? null;
        $intentId = $intent->id ?? null;

        $this->markPendingPaymentFailed(
            null,
            is_string($paymentId) ? $paymentId : null,
            is_string($intentId) ? $intentId : null,
        );
    }

    /**
     * pending の決済だけを failed に変更する。
     */
    private function markPendingPaymentFailed(
        ?string $sessionId,
        ?string $paymentId,
        ?string $intentId = null,
    ): void {
        DB::transaction(function () use ($sessionId, $paymentId, $intentId): void {
            $query = Payment::query()->lockForUpdate();

            if ($paymentId !== null && $paymentId !== '') {
                $query->whereKey($paymentId);
            } elseif ($sessionId !== null && $sessionId !== '') {
                $query->where('stripe_checkout_session_id', $sessionId);
            } elseif ($intentId !== null && $intentId !== '') {
                $query->where('stripe_payment_intent_id', $intentId);
            } else {
                return;
            }

            $payment = $query->first();

            if ($payment?->status === PaymentStatus::Pending) {
                $payment->update([
                    'status' => PaymentStatus::Failed,
                ]);
            }
        });
    }
}
