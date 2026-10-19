<?php

declare(strict_types=1);

namespace App\UseCases\MeetingPack;

use App\Enums\MeetingPackStatus;
use App\Exceptions\MeetingPack\MeetingPackNotDeletableException;
use App\Models\MeetingPack;
use Illuminate\Support\Facades\DB;

final class DestroyAction
{
    public function __invoke(MeetingPack $meetingPack): void
    {
        DB::transaction(function () use ($meetingPack): void {
            $lockedPack = MeetingPack::query()
                ->whereKey($meetingPack->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (
                $lockedPack->status === MeetingPackStatus::Published
                || $lockedPack->payments()->exists()
            ) {
                throw new MeetingPackNotDeletableException;
            }

            $lockedPack->delete();
        });
    }
}
