<?php

declare(strict_types=1);

namespace App\UseCases\EnrollmentGoal;

use App\Models\EnrollmentGoal;

final class MarkAchievedAction
{
    /**
     * 個人学習目標を達成済みにする。
     */
    public function __invoke(EnrollmentGoal $goal): void
    {
        $goal->update([
            'achieved_at' => now(),
        ]);
    }
}
