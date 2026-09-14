<?php

declare(strict_types=1);

namespace App\UseCases\Plan;

use App\Enums\UserStatus;
use App\Models\Plan;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class ShowAction
{
    public function __invoke(Plan $plan): Plan
    {
        return $plan->load([
            'users' => function (HasMany $query): void {
                $query->where('status', UserStatus::InProgress);
            },
            'createdBy',
            'updatedBy',
        ]);
    }
}
