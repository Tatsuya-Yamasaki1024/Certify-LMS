<?php

declare(strict_types=1);

namespace App\UseCases\EnrollmentGoal;

use App\Models\Enrollment;
use App\Models\EnrollmentGoal;

final class StoreAction
{
    /**
     * 個人学習目標を作成する。
     *
     * @param array{
     *     title: string,
     *     description?: string|null,
     *     target_date?: string|null
     * } $data
     */
    public function __invoke(Enrollment $enrollment, array $data): EnrollmentGoal
    {
        return $enrollment->goals()->create([
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'target_date' => $data['target_date'] ?? null,
        ]);
    }
}
