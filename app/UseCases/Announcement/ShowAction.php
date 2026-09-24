<?php

declare(strict_types=1);

namespace App\UseCases\Announcement;

use App\Models\Announcement;

final class ShowAction
{
    /**
     * お知らせ詳細を取得する。
     */
    public function __invoke(Announcement $announcement): Announcement
    {
        return $announcement->load([
            'targetCertification',
            'targetUser',
            'createdBy',
        ]);
    }
}
