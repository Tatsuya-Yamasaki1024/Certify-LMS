<?php

declare(strict_types=1);

namespace App\UseCases\Plan;

use App\Enums\PlanStatus;
use App\Enums\UserStatus;
use App\Models\Plan;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

final class IndexAction
{
    public function __invoke(
        ?string $keyword,
        ?PlanStatus $status,
        int $perPage = 20,
    ): LengthAwarePaginator {
        $query = Plan::query();

        if ($keyword !== null && $keyword !== '') {
            $normalizedKeyword = str_replace([' ', '　'], '', $keyword);

            $query->whereRaw(
                "REPLACE(REPLACE(name, ' ', ''), '　', '') LIKE ?",
                ['%'.$normalizedKeyword.'%']
            );
        }

        if ($status !== null) {
            $query->where('status', $status->value);
        }

        return $query
            ->withCount([
                'users as users_count' => function (Builder $query): void {
                    $query->where('status', UserStatus::InProgress);
                },
            ])
            ->ordered()
            ->paginate($perPage)
            ->withQueryString();
    }
}
