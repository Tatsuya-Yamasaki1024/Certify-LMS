<?php

declare(strict_types=1);

namespace App\Services\AiChat;

use App\Enums\CertificationStatus;
use App\Enums\ContentStatus;
use App\Enums\EnrollmentStatus;
use App\Models\Enrollment;
use App\Models\Section;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * AI チャットに渡す資格・教材コンテキストを構築するサービス。
 */
final class AiChatContextService
{
    /**
     * 起動元に応じて会話のコンテキストと受講登録を取得する。
     *
     * @return array{
     *     enrollment: Enrollment|null,
     *     section: Section|null,
     *     context: string
     * }
     */
    public function build(User $student, ?string $sectionId = null): array
    {
        if ($sectionId !== null && $sectionId !== '') {
            return $this->buildFromSection($student, $sectionId);
        }

        $enrollment = $student->enrollments()
            ->where('id', $student->default_enrollment_id)
            ->whereIn('status', [
                EnrollmentStatus::Learning->value,
                EnrollmentStatus::Passed->value,
            ])
            ->with('certification')
            ->first();

        $certificationName = $enrollment?->certification?->name;

        return [
            'enrollment' => $enrollment,
            'section' => null,
            'context' => $certificationName !== null
                ? "受講資格: {$certificationName}"
                : '',
        ];
    }

    /**
     * 教材画面から起動した会話のコンテキストを構築する。
     *
     * @return array{
     *     enrollment: Enrollment,
     *     section: Section,
     *     context: string
     * }
     */
    private function buildFromSection(User $student, string $sectionId): array
    {
        $section = Section::query()
            ->with('chapter.part.certification')
            ->findOrFail($sectionId);

        Gate::authorize('learning.section.view', $section);

        $chapter = $section->chapter;
        $part = $chapter?->part;
        $certification = $part?->certification;

        if (
            $section->status !== ContentStatus::Published
            || $chapter === null
            || $chapter->status !== ContentStatus::Published
            || $part === null
            || $part->status !== ContentStatus::Published
            || $certification === null
            || $certification->status !== CertificationStatus::Published
        ) {
            throw new NotFoundHttpException;
        }

        $enrollment = $student->enrollments()
            ->where('certification_id', $certification->id)
            ->whereIn('status', [
                EnrollmentStatus::Learning->value,
                EnrollmentStatus::Passed->value,
            ])
            ->with('certification')
            ->first();

        if ($enrollment === null) {
            throw new NotFoundHttpException;
        }

        $context = implode("\n", [
            "受講資格: {$certification->name}",
            '現在の教材:',
            "Part {$part->order}: {$part->title}",
            "Chapter {$chapter->order}: {$chapter->title}",
            "Section {$section->order}: {$section->title}",
        ]);

        return [
            'enrollment' => $enrollment,
            'section' => $section,
            'context' => $context,
        ];
    }
}
