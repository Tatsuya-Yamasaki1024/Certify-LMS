<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\MeetingPack;
use App\Models\Payment;
use App\Models\User;
use Stripe\Checkout\Session;
use Stripe\StripeClient;

/**
 * Stripe Checkout Session の作成を担当する。
 */
class StripeCheckoutSessionCreator
{
    /**
     * Checkout Session を作成する。
     */
    public function create(
        User $user,
        MeetingPack $meetingPack,
        Payment $payment,
    ): Session {
        $secret = config('services.stripe.secret');

        if (! is_string($secret) || $secret === '') {
            throw new \RuntimeException('Stripe secret key is not configured.');
        }

        $client = new StripeClient($secret);

        return $client->checkout->sessions->create([
            'mode' => 'payment',
            'payment_method_types' => ['card'],
            'client_reference_id' => $payment->id,
            'customer_email' => $user->email,
            'metadata' => [
                'payment_id' => $payment->id,
                'user_id' => $user->id,
                'meeting_pack_id' => $meetingPack->id,
            ],
            'payment_intent_data' => [
                'metadata' => [
                    'payment_id' => $payment->id,
                    'user_id' => $user->id,
                    'meeting_pack_id' => $meetingPack->id,
                ],
            ],
            'line_items' => [
                [
                    'price_data' => [
                        'currency' => config('services.stripe.currency', 'jpy'),
                        'unit_amount' => $meetingPack->price,
                        'product_data' => [
                            'name' => $meetingPack->name,
                            'description' => $meetingPack->description
                                ?: "{$meetingPack->meeting_count}回分の追加面談",
                        ],
                    ],
                    'quantity' => 1,
                ],
            ],
            'success_url' => route('meeting-quota.checkout.success')
                .'?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => route('meeting-quota.checkout.index'),
        ]);
    }
}
