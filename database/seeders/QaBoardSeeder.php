<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\QaThreadStatus;
use App\Models\Certification;
use App\Models\QaReply;
use App\Models\QaThread;
use App\Models\User;
use Illuminate\Database\Seeder;

class QaBoardSeeder extends Seeder
{
    public function run(): void
    {
        $student = User::query()
            ->where('email', 'student@certify-lms.test')
            ->first();

        $otherStudent = User::query()
            ->where('email', 'student-noquota@certify-lms.test')
            ->first();

        $coach = User::query()
            ->where('email', 'coach@certify-lms.test')
            ->first();

        if ($student === null) {
            $this->command?->warn(
                'QaBoardSeeder: 固定受講生が存在しません。先に UserSeeder を実行してください。'
            );

            return;
        }

        $certifications = Certification::query()
            ->where('status', 'published')
            ->orderBy('created_at')
            ->get();

        if ($certifications->isEmpty()) {
            $this->command?->warn(
                'QaBoardSeeder: 公開済み資格が存在しません。先に CertificationSeeder を実行してください。'
            );

            return;
        }

        foreach ($certifications as $index => $certification) {
            $this->createThreads(
                $certification,
                $student,
                $otherStudent,
                $coach,
                $index
            );
        }
    }

    private function createThreads(
        Certification $certification,
        User $student,
        ?User $otherStudent,
        ?User $coach,
        int $index
    ): void {
        $baseDays = 20 + ($index * 3);

        // 未解決・回答なし
        QaThread::factory()->create([
            'user_id' => $student->id,
            'certification_id' => $certification->id,
            'title' => $certification->name.'の勉強方法について',
            'body' => '効率よく学習するためのおすすめの勉強方法を教えてください。',
            'status' => QaThreadStatus::Unresolved->value,
            'resolved_at' => null,
            'created_at' => now()->subDays($baseDays),
            'updated_at' => now()->subDays($baseDays),
        ]);

        // 解決済み・回答2件
        $resolvedThread = QaThread::factory()->create([
            'user_id' => $student->id,
            'certification_id' => $certification->id,
            'title' => $certification->name.'の試験対策について',
            'body' => '試験前に重点的に確認しておいた方がよい範囲はありますか？',
            'status' => QaThreadStatus::Resolved->value,
            'resolved_at' => now()->subDays($baseDays - 4),
            'created_at' => now()->subDays($baseDays - 8),
            'updated_at' => now()->subDays($baseDays - 4),
        ]);

        if ($coach !== null) {
            QaReply::factory()->create([
                'qa_thread_id' => $resolvedThread->id,
                'user_id' => $coach->id,
                'body' => 'まずは公式の出題範囲を確認し、過去問題を繰り返し解くことをおすすめします。',
                'created_at' => now()->subDays($baseDays - 7),
                'updated_at' => now()->subDays($baseDays - 7),
            ]);

            QaReply::factory()->create([
                'qa_thread_id' => $resolvedThread->id,
                'user_id' => $student->id,
                'body' => 'ありがとうございます。過去問題を中心に復習してみます。',
                'created_at' => now()->subDays($baseDays - 4),
                'updated_at' => now()->subDays($baseDays - 4),
            ]);
        }

        // 未解決・回答1件
        $unresolvedThread = QaThread::factory()->create([
            'user_id' => $otherStudent?->id ?? $student->id,
            'certification_id' => $certification->id,
            'title' => $certification->name.'について質問があります',
            'body' => 'この分野の問題がなかなか理解できません。勉強のコツを教えてください。',
            'status' => QaThreadStatus::Unresolved->value,
            'resolved_at' => null,
            'created_at' => now()->subDays($baseDays - 12),
            'updated_at' => now()->subDays($baseDays - 10),
        ]);

        if ($coach !== null) {
            QaReply::factory()->create([
                'qa_thread_id' => $unresolvedThread->id,
                'user_id' => $coach->id,
                'body' => 'まずは基礎的な問題を繰り返し解いて、理解できていない部分を確認してみてください。',
                'created_at' => now()->subDays($baseDays - 10),
                'updated_at' => now()->subDays($baseDays - 10),
            ]);
        }
    }
}
