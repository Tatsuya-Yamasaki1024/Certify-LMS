<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\MeetingPackStatus;
use App\Enums\MeetingQuotaTransactionType;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\MeetingPack;
use App\Models\MeetingQuotaTransaction;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * 開発用の面談パック購入履歴を作成する。
 *
 * 決済成功・保留・失敗の状態を網羅する。
 * 成功した決済のみ面談回数を付与する。
 */
class PaymentSeeder extends Seeder
{
    public function run(): void
    {
        $packs = MeetingPack::query()
            ->where('status', MeetingPackStatus::Published->value)
            ->orderBy('sort_order')
            ->get();

        if ($packs->count() < 3) {
            $this->command?->warn('PaymentSeeder: 公開済み面談パックが不足しています。');

            return;
        }

        $fixedStudent = User::query()
            ->where('email', 'student@certify-lms.test')
            ->first();

        $demoStudents = User::query()
            ->where('role', UserRole::Student->value)
            ->where('status', UserStatus::InProgress->value)
            ->whereNotIn('email', [
                'student@certify-lms.test',
                'student-noquota@certify-lms.test',
            ])
            ->orderBy('id')
            ->limit(3)
            ->get();

        if ($fixedStudent === null || $demoStudents->count() < 3) {
            $this->command?->warn('PaymentSeeder: 対象となる受講生が不足しています。');

            return;
        }

        $students = collect([$fixedStudent])
            ->concat($demoStudents);

        foreach ($students as $student) {
            foreach (
                [
                    PaymentStatus::Succeeded,
                    PaymentStatus::Pending,
                    PaymentStatus::Failed,
                ] as $index => $status
            ) {
                $pack = $packs[$index];

                $this->createPayment(
                    $student,
                    $pack,
                    $status,
                    $index,
                );
            }
        }
    }

    /**
     * 購入履歴を作成し、決済成功時のみ面談回数を付与する。
     */
    private function createPayment(
        User $student,
        MeetingPack $pack,
        PaymentStatus $status,
        int $index,
    ): void {
        DB::transaction(function () use ($student, $pack, $status, $index): void {
            $sessionId = "cs_seed_{$student->id}_{$index}";

            $payment = Payment::query()->firstOrCreate(
                [
                    'stripe_checkout_session_id' => $sessionId,
                ],
                [
                    'user_id' => $student->id,
                    'meeting_pack_id' => $pack->id,
                    'status' => $status,
                    'amount' => $pack->price,
                    'quantity' => 1,
                    'meeting_count' => $pack->meeting_count,
                    'stripe_payment_intent_id' => $status === PaymentStatus::Succeeded
                        ? "pi_seed_{$student->id}_{$index}"
                        : null,
                    'paid_at' => $status === PaymentStatus::Succeeded
                        ? now()->subDays(3)
                        : null,
                ],
            );

            if ($payment->status !== PaymentStatus::Succeeded) {
                return;
            }

            MeetingQuotaTransaction::query()->firstOrCreate(
                [
                    'related_payment_id' => $payment->id,
                    'type' => MeetingQuotaTransactionType::Purchased,
                ],
                [
                    'user_id' => $student->id,
                    'amount' => $payment->meeting_count * $payment->quantity,
                    'note' => "面談パック購入: {$pack->name}",
                    'occurred_at' => $payment->paid_at ?? now(),
                ],
            );
        });
    }
}
