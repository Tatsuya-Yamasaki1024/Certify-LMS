<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\AiChatMessageRole;
use App\Enums\AiChatMessageStatus;
use App\Models\AiChatConversation;
use App\Models\Enrollment;
use App\Models\Section;
use App\Models\User;
use Illuminate\Database\Seeder;

final class AiChatSeeder extends Seeder
{
    public function run(): void
    {
        $student = User::query()
            ->where('email', 'student@certify-lms.test')
            ->first();

        if ($student === null) {
            $this->command?->warn('AiChatSeeder: 固定受講生が存在しません。');

            return;
        }

        $enrollment = Enrollment::query()
            ->where('user_id', $student->id)
            ->whereHas('certification', fn ($query) => $query
                ->where('name', '基本情報技術者試験'))
            ->first();

        if ($enrollment === null) {
            $this->command?->warn('AiChatSeeder: 対象の受講登録が存在しません。');

            return;
        }

        $section = Section::query()
            ->published()
            ->whereHas('chapter.part', fn ($query) => $query
                ->where('certification_id', $enrollment->certification_id))
            ->where('title', '1.1 2 進数の表現')
            ->first();

        if ($section === null) {
            $this->command?->warn('AiChatSeeder: 公開済みの対象Sectionが存在しません。');

            return;
        }

        $this->seedConversation(
            $student,
            $enrollment,
            $section,
            '2進数の学習相談',
            [
                [AiChatMessageRole::User, AiChatMessageStatus::Completed, '10進数を2進数に変換する方法を教えてください。'],
                [AiChatMessageRole::Assistant, AiChatMessageStatus::Completed, '10進数を2で繰り返し割り、余りを下から順に並べると2進数に変換できます。'],
                [AiChatMessageRole::User, AiChatMessageStatus::Completed, '25を2進数にするとどうなりますか？'],
                [AiChatMessageRole::Assistant, AiChatMessageStatus::Completed, '25を2進数にすると11001です。16+8+1=25となります。'],
            ],
            3,
        );

        $this->seedConversation(
            $student,
            $enrollment,
            null,
            '試験対策の学習計画',
            [
                [AiChatMessageRole::User, AiChatMessageStatus::Completed, '基本情報技術者試験の学習計画を立てたいです。'],
                [AiChatMessageRole::Assistant, AiChatMessageStatus::Completed, 'まず試験日までの期間を確認し、基礎学習・問題演習・復習の3段階に分けましょう。'],
                [AiChatMessageRole::User, AiChatMessageStatus::Completed, '毎日1時間の場合はどう進めればよいですか？'],
                [AiChatMessageRole::Assistant, AiChatMessageStatus::Completed, '最初の30分で教材を学び、残り30分で問題演習と復習を行う方法がおすすめです。'],
            ],
            2,
        );

        $this->seedConversation(
            $student,
            $enrollment,
            null,
            'AI応答エラーの確認',
            [
                [AiChatMessageRole::User, AiChatMessageStatus::Error, 'アルゴリズムの計算量について詳しく教えてください。'],
            ],
            1,
        );
    }

    /**
     * 会話とメッセージを重複させずに投入する。
     *
     * @param array<int, array{AiChatMessageRole, AiChatMessageStatus, string}> $messages
     */
    private function seedConversation(
        User $student,
        Enrollment $enrollment,
        ?Section $section,
        string $title,
        array $messages,
        int $daysAgo,
    ): void {
        $conversation = AiChatConversation::query()->firstOrCreate(
            [
                'user_id' => $student->id,
                'title' => $title,
            ],
            [
                'enrollment_id' => $enrollment->id,
                'section_id' => $section?->id,
            ],
        );

        $baseTime = now()->subDays($daysAgo);

        foreach ($messages as $index => [$role, $status, $content]) {
            $createdAt = $baseTime->copy()->addMinutes($index * 5);

            $message = $conversation->messages()->firstOrCreate(
                [
                    'role' => $role->value,
                    'content' => $content,
                ],
                [
                    'status' => $status->value,
                    'error_detail' => $status === AiChatMessageStatus::Error
                        ? 'Gemini API の応答取得に失敗しました。'
                        : null,
                    'model' => $role === AiChatMessageRole::Assistant
                        ? 'gemini-3.1-flash-lite'
                        : null,
                ],
            );

            if ($message->wasRecentlyCreated) {
                $message->forceFill([
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ])->save();
            }
        }

        $conversation->update([
            'last_message_at' => $conversation->messages()->max('created_at'),
        ]);
    }
}
