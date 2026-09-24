<?php

declare(strict_types=1);

namespace App\Console\Commands\Mentoring;

use App\Enums\MeetingReminderType;
use App\Enums\MeetingStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Meeting;
use App\Models\MeetingReminderDelivery;
use App\Notifications\Meeting\MeetingReminderNotification;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\UniqueConstraintViolationException;

class SendMeetingRemindersCommand extends Command
{
    protected $signature = 'notifications:send-meeting-reminders {--window= : リマインダー種別（eve / one_hour_before）}';

    protected $description = '面談のリマインダー通知を送信する。';

    // 面談リマインダー通知を送信する。
    public function handle(): int
    {
        $reminderType = MeetingReminderType::tryFrom((string) $this->option('window'));

        if ($reminderType === null) {
            $this->error('windowには eve または one_hour_before を指定してください。');

            return self::FAILURE;
        }

        $meetings = $this->getTargetMeetings($reminderType);

        foreach ($meetings as $meeting) {
            $this->sendReminder($meeting, $reminderType);
        }

        $this->info('対象の面談件数：'.$meetings->count());

        return self::SUCCESS;
    }

    /**
     * リマインダー種別に応じた対象面談を取得する。
     *
     * @return Collection<int, Meeting>
     */
    private function getTargetMeetings(MeetingReminderType $reminderType): Collection
    {
        $query = Meeting::query()
            ->where('status', MeetingStatus::Reserved->value)
            ->with(['student', 'coach']);

        return match ($reminderType) {
            MeetingReminderType::Eve => $query
                ->whereBetween('scheduled_at', [
                    now()->addDay()->startOfDay(),
                    now()->addDay()->endOfDay(),
                ])
                ->orderBy('scheduled_at')
                ->get(),

            MeetingReminderType::OneHourBefore => $query
                ->whereBetween('scheduled_at', [
                    now()->startOfHour()->addHour(),
                    now()->startOfHour()->addHour()->endOfHour(),
                ])
                ->orderBy('scheduled_at')
                ->get(),
        };
    }

    /**
     * 面談の参加者へリマインダーを送信する。
     */
    private function sendReminder(
        Meeting $meeting,
        MeetingReminderType $reminderType,
    ): void {
        collect([$meeting->student, $meeting->coach])
            ->filter()
            ->filter(
                fn ($user): bool => $user->status === UserStatus::InProgress
                    && in_array($user->role, [
                        UserRole::Student,
                        UserRole::Coach,
                    ], true),
            )
            ->each(function ($user) use ($meeting, $reminderType): void {
                try {
                    MeetingReminderDelivery::create([
                        'meeting_id' => $meeting->id,
                        'user_id' => $user->id,
                        'reminder_type' => $reminderType,
                    ]);
                } catch (UniqueConstraintViolationException) {
                    return;
                }

                $user->notify(
                    new MeetingReminderNotification(
                        $meeting,
                        $reminderType,
                    ),
                );
            });
    }
}
