<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\AnnouncementTargetType;
use App\Enums\CertificationStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Announcement;
use App\Models\Certification;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * 開発用 運営お知らせシーダー。
 *
 * 全受講生・資格指定・ユーザー指定の3種類のお知らせと、
 * それぞれに対応するデータベース通知を生成する。
 */
final class AnnouncementSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()
            ->where('role', UserRole::Admin->value)
            ->orderBy('created_at')
            ->first();

        $student = User::query()
            ->where('email', 'student@certify-lms.test')
            ->where('role', UserRole::Student->value)
            ->where('status', UserStatus::InProgress->value)
            ->first();

        $certification = Certification::query()
            ->where('status', CertificationStatus::Published->value)
            ->where('name', '基本情報技術者試験')
            ->first();

        if ($admin === null || $student === null || $certification === null) {
            $this->command?->warn(
                'AnnouncementSeeder: お知らせの初期データに必要なユーザー・資格が存在しません。'
            );

            return;
        }

        $this->createAllStudentsAnnouncement($admin);
        $this->createCertificationAnnouncement($admin, $certification);
        $this->createUserAnnouncement($admin, $student);
    }

    /**
     * 全受講生向けのお知らせを作成する。
     */
    private function createAllStudentsAnnouncement(User $admin): void
    {
        $this->createAnnouncement(
            admin: $admin,
            title: '運営からのお知らせ',
            body: '受講生の皆さまへ、運営からのお知らせです。',
            targetType: AnnouncementTargetType::AllStudents,
        );
    }

    /**
     * 資格指定のお知らせを作成する。
     */
    private function createCertificationAnnouncement(
        User $admin,
        Certification $certification,
    ): void {
        $this->createAnnouncement(
            admin: $admin,
            title: '基本情報技術者試験コースのお知らせ',
            body: '基本情報技術者試験を受講中の皆さまへのお知らせです。',
            targetType: AnnouncementTargetType::Certification,
            targetCertificationId: $certification->id,
        );
    }

    /**
     * ユーザー指定のお知らせを作成する。
     */
    private function createUserAnnouncement(User $admin, User $student): void
    {
        $this->createAnnouncement(
            admin: $admin,
            title: '受講に関する個別のお知らせ',
            body: '受講中の皆さまへの個別のお知らせです。',
            targetType: AnnouncementTargetType::User,
            targetUserId: $student->id,
        );
    }

    /**
     * お知らせと対応するデータベース通知を作成する。
     */
    private function createAnnouncement(
        User $admin,
        string $title,
        string $body,
        AnnouncementTargetType $targetType,
        ?string $targetCertificationId = null,
        ?string $targetUserId = null,
    ): void {
        $announcement = Announcement::firstOrCreate(
            [
                'title' => $title,
            ],
            [
                'body' => $body,
                'target_type' => $targetType,
                'target_certification_id' => $targetCertificationId,
                'target_user_id' => $targetUserId,
                'created_by_user_id' => $admin->id,
            ],
        );

        $recipients = $this->recipients(
            $targetType,
            $targetCertificationId,
            $targetUserId,
        );

        $createdAt = $announcement->dispatched_at ?? Carbon::now();

        foreach ($recipients as $recipient) {
            $notificationExists = $recipient->notifications()
                ->where('type', 'App\\Notifications\\Announcement\\AdminAnnouncementNotification')
                ->where('data->announcement_id', $announcement->id)
                ->exists();

            if ($notificationExists) {
                continue;
            }

            $recipient->notifications()->create([
                'id' => (string) Str::uuid(),
                'type' => 'App\\Notifications\\Announcement\\AdminAnnouncementNotification',
                'data' => [
                    'notification_type' => 'admin_announcement',
                    'title' => $announcement->title,
                    'message' => $announcement->body,
                    'body' => $announcement->body,
                    'announcement_id' => $announcement->id,
                ],
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);
        }

        $dispatchedCount = $recipients->count();

        $announcement->update([
            'dispatched_count' => $dispatchedCount,
            'dispatched_at' => $createdAt,
        ]);
    }

    /**
     * お知らせの配信対象となる受講生を取得する。
     *
     * @return Collection<int, User>
     */
    private function recipients(
        AnnouncementTargetType $targetType,
        ?string $targetCertificationId,
        ?string $targetUserId,
    ) {
        $query = User::query()
            ->where('role', UserRole::Student->value)
            ->where('status', UserStatus::InProgress->value);

        return match ($targetType) {
            AnnouncementTargetType::AllStudents => $query->get(),

            AnnouncementTargetType::Certification => $query
                ->whereHas(
                    'enrollments',
                    fn ($query) => $query->where(
                        'certification_id',
                        $targetCertificationId,
                    )
                )
                ->get(),

            AnnouncementTargetType::User => $query
                ->whereKey($targetUserId)
                ->get(),
        };
    }
}
