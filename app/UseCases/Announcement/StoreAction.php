<?php

declare(strict_types=1);

namespace App\UseCases\Announcement;

use App\Enums\AnnouncementTargetType;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Announcement;
use App\Models\User;
use App\Notifications\Announcement\AdminAnnouncementNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

final class StoreAction
{
    /**
     * お知らせを作成し、対象の受講生へ配信する。
     *
     * @param array<string, mixed> $validated
     */
    public function __invoke(User $admin, array $validated): Announcement
    {
        $announcement = DB::transaction(
            fn (): Announcement => Announcement::create([
                'title' => $validated['title'],
                'body' => $validated['body'],
                'target_type' => $validated['target_type'],
                'target_certification_id' => $validated['target_type'] === AnnouncementTargetType::Certification->value
                    ? $validated['target_certification_id'] ?? null
                    : null,
                'target_user_id' => $validated['target_type'] === AnnouncementTargetType::User->value
                    ? $validated['target_user_id'] ?? null
                    : null,
                'created_by_user_id' => $admin->id,
            ])
        );

        DB::afterCommit(function () use ($announcement): void {
            $recipients = $this->recipients($announcement);

            foreach ($recipients as $recipient) {
                $announcement->increment('dispatched_count');

                $recipient->notify(
                    new AdminAnnouncementNotification($announcement)
                );
            }

            $announcement->update([
                'dispatched_at' => now(),
            ]);
        });

        return $announcement;
    }

    /**
     * お知らせの配信対象となる受講生を取得する。
     *
     * @return Collection<int, User>
     */
    private function recipients(Announcement $announcement): Collection
    {
        $query = User::query()
            ->where('role', UserRole::Student->value)
            ->where('status', UserStatus::InProgress->value);

        return match ($announcement->target_type) {
            AnnouncementTargetType::AllStudents => $query->get(),

            AnnouncementTargetType::Certification => $query
                ->whereHas(
                    'enrollments',
                    fn (Builder $query): Builder => $query->where(
                        'certification_id',
                        $announcement->target_certification_id,
                    )
                )
                ->get(),

            AnnouncementTargetType::User => $query
                ->whereKey($announcement->target_user_id)
                ->get(),
        };
    }
}
