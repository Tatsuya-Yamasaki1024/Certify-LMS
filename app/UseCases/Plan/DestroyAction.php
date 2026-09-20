<?php

declare(strict_types=1);

namespace App\UseCases\Plan;

use App\Enums\PlanStatus;
use App\Exceptions\Plan\PlanNotDeletableException;
use App\Models\Plan;
use Illuminate\Support\Facades\DB;

final class DestroyAction
{
    public function __invoke(Plan $plan): void
    {
        if ($plan->status !== PlanStatus::Draft) {
            throw new PlanNotDeletableException(
                '下書きのプランのみ削除できます。削除する場合は下書きに変更してください。',
            );
        }

        if ($plan->users()->exists() || $plan->userPlanLogs()->exists()) {
            throw new PlanNotDeletableException;
        }

        DB::transaction(fn (): ?bool => $plan->delete());
    }
}
