<?php

declare(strict_types=1);

namespace App\Http\Controllers\MeetingQuota;

use App\Enums\MeetingPackStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\MeetingPack;
use App\Models\Payment;
use App\Services\StripeCheckoutSessionCreator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Throwable;

/**
 * 追加面談パックの購入処理を担当する。
 */
final class CheckoutController extends Controller
{
    /**
     * 購入可能な面談パック一覧を表示する。
     */
    public function index(): View
    {
        return view('meeting-quota.checkout-select', [
            'plans' => MeetingPack::query()
                ->published()
                ->ordered()
                ->get(),
        ]);
    }

    /**
     * Stripe Checkout Session を作成し、決済画面へ遷移する。
     */
    public function store(
        Request $request,
        StripeCheckoutSessionCreator $sessionCreator,
    ): RedirectResponse {
        $validated = $request->validate([
            'meeting_pack_id' => ['required', 'string', 'ulid'],
        ]);

        $meetingPack = MeetingPack::query()
            ->published()
            ->whereKey($validated['meeting_pack_id'])
            ->first();

        if ($meetingPack === null) {
            return redirect()
                ->route('meeting-quota.checkout.index')
                ->with('error', '選択した面談パックは購入できません。');
        }

        $payment = DB::transaction(function () use ($request, $meetingPack): Payment {
            $lockedPack = MeetingPack::query()
                ->whereKey($meetingPack->id)
                ->lockForUpdate()
                ->first();

            if ($lockedPack === null || $lockedPack->status !== MeetingPackStatus::Published) {
                abort(404);
            }

            return Payment::query()->create([
                'user_id' => $request->user()->id,
                'meeting_pack_id' => $lockedPack->id,
                'status' => PaymentStatus::Pending,
                'amount' => $lockedPack->price,
                'quantity' => 1,
            ]);
        });

        try {
            $session = $sessionCreator->create(
                $request->user(),
                $meetingPack,
                $payment,
            );

            if (! is_string($session->url) || $session->url === '') {
                throw new \RuntimeException('Stripe Checkout URL was not returned.');
            }

            $payment->update([
                'stripe_checkout_session_id' => $session->id,
            ]);

            return redirect()->away($session->url);
        } catch (Throwable $exception) {
            $payment->update([
                'status' => PaymentStatus::Failed,
            ]);

            Log::error('Stripe Checkout Session creation failed.', [
                'payment_id' => $payment->id,
                'exception' => $exception,
            ]);

            return redirect()
                ->route('meeting-quota.checkout.index')
                ->with('error', '決済画面を作成できませんでした。時間をおいて再度お試しください。');
        }
    }

    /**
     * Stripe Checkout から戻った受講生に決済状態を表示する。
     *
     * 面談回数の加算は行わず、Webhook による確定処理に任せる。
     */
    public function success(Request $request): View
    {
        $validated = $request->validate([
            'session_id' => ['nullable', 'string'],
        ]);

        $payment = null;

        if (isset($validated['session_id'])) {
            $payment = Payment::query()
                ->with('meetingPack')
                ->where('user_id', $request->user()->id)
                ->where('stripe_checkout_session_id', $validated['session_id'])
                ->first();
        }

        return view('meeting-quota.success', [
            'payment' => $payment,
        ]);
    }
}
