<?php

declare(strict_types=1);

namespace App\UseCases\EnrollmentGoal;

use App\Models\EnrollmentGoal;

final class UpdateAction
{
    /**
     * 個人学習目標を更新する。
     *
     * @param array{
     *     title: string,
     *     description?: string|null,
     *     target_date?: string|null
     * } $data
     */
    public function __invoke(EnrollmentGoal $goal, array $data): EnrollmentGoal
    {
        $goal->update([
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'target_date' => $data['target_date'] ?? null,
        ]);

        return $goal->refresh();
    }
}
