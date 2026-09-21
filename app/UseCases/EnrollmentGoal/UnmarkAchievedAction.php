<?php

declare(strict_types=1);

namespace App\UseCases\EnrollmentGoal;

use App\Models\EnrollmentGoal;

final class UnmarkAchievedAction
{
    /**
     * 個人学習目標を未達成に戻す。
     */
    public function __invoke(EnrollmentGoal $goal): void
    {
        $goal->update([
            'achieved_at' => null,
        ]);
    }
}
