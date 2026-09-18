<?php

declare(strict_types=1);

namespace App\UseCases\Plan;

use App\Models\Plan;

final class ShowAction
{
    public function __invoke(Plan $plan): Plan
    {
        return $plan->load([
            'users',
            'createdBy',
            'updatedBy',
        ]);
    }
}
