<?php

declare(strict_types=1);

namespace App\UseCases\MeetingPack;

use App\Models\MeetingPack;

final class ShowAction
{
    /**
     * 面談パックの詳細と直近の購入履歴を取得する。
     */
    public function __invoke(MeetingPack $meetingPack): MeetingPack
    {
        return $meetingPack
            ->loadCount('payments')
            ->load([
                'createdBy',
                'updatedBy',
                'payments' => fn ($query) => $query
                    ->with('user')
                    ->latest()
                    ->limit(20),
            ]);
    }
}
