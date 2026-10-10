<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Exceptions\Mentoring\MeetingOutOfAvailabilityException;
use App\Models\Certification;
use App\Models\CoachAvailability;
use App\Models\GoogleCredential;
use App\Models\Meeting;
use App\Models\User;
use App\Services\GoogleCalendarService;
use App\Services\MeetingAvailabilityService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class MeetingAvailabilityServiceTest extends TestCase
{
    use RefreshDatabase;

    private function attachCoach(Certification $certification, User $coach): void
    {
        $certification->coaches()->attach($coach->id, [
            'id' => (string) Str::ulid(),
            'assigned_by_user_id' => User::factory()->admin()->create()->id,
            'assigned_at' => now(),
            'unassigned_at' => null,
        ]);
    }

    // 有効な面談可能時間から60分単位の予約枠を取得できることを確認する
    public function test_returns_60min_slots_for_active_availability(): void
    {
        $certification = Certification::factory()->published()->create();
        $coach = User::factory()->coach()->create();
        $this->attachCoach($certification, $coach);

        $date = Carbon::parse('2026-06-01'); // Monday (dayOfWeek=1)
        CoachAvailability::factory()->forCoach($coach)->onDay(1)->timeRange('09:00:00', '12:00:00')->create();

        $slots = app(MeetingAvailabilityService::class)->slotsForCertification($certification, $date);

        // 09-10 / 10-11 / 11-12 の 3 枠
        $this->assertCount(3, $slots);
        $this->assertSame('2026-06-01T09:00:00+09:00', $slots->first()['slot_start']->toIso8601String());
        $this->assertSame(1, $slots->first()['available_coach_count']);
    }

    // 予約済みの面談と重複する予約枠を除外することを確認する
    public function test_excludes_existing_reserved_meetings(): void
    {
        $certification = Certification::factory()->published()->create();
        $coach = User::factory()->coach()->create();
        $student = User::factory()->student()->create();
        $this->attachCoach($certification, $coach);

        $date = Carbon::parse('2026-06-01');
        CoachAvailability::factory()->forCoach($coach)->onDay(1)->timeRange('09:00:00', '12:00:00')->create();
        // 10:00 にすでに予約あり
        Meeting::factory()->reserved()->forCoach($coach)->forStudent($student)->create([
            'scheduled_at' => Carbon::parse('2026-06-01 10:00:00'),
        ]);

        $slots = app(MeetingAvailabilityService::class)->slotsForCertification($certification, $date);

        $times = $slots->map(fn (array $s) => $s['slot_start']->format('H:i'))->all();
        $this->assertEquals(['09:00', '11:00'], $times);
    }

    // 無効化された面談可能時間から予約枠が生成されないことを確認する
    public function test_excludes_inactive_availability(): void
    {
        $certification = Certification::factory()->published()->create();
        $coach = User::factory()->coach()->create();
        $this->attachCoach($certification, $coach);

        $date = Carbon::parse('2026-06-01');
        CoachAvailability::factory()->forCoach($coach)->onDay(1)->timeRange('09:00:00', '11:00:00')->inactive()->create();

        $slots = app(MeetingAvailabilityService::class)->slotsForCertification($certification, $date);

        $this->assertCount(0, $slots);
    }

    // 複数のコーチの予約可能枠を統合し、対応可能なコーチ数を集計することを確認する
    public function test_unions_multiple_coaches_into_available_count(): void
    {
        $certification = Certification::factory()->published()->create();
        $coachA = User::factory()->coach()->create();
        $coachB = User::factory()->coach()->create();
        $this->attachCoach($certification, $coachA);
        $this->attachCoach($certification, $coachB);

        $date = Carbon::parse('2026-06-01');
        CoachAvailability::factory()->forCoach($coachA)->onDay(1)->timeRange('09:00:00', '10:00:00')->create();
        CoachAvailability::factory()->forCoach($coachB)->onDay(1)->timeRange('09:00:00', '10:00:00')->create();

        $slots = app(MeetingAvailabilityService::class)->slotsForCertification($certification, $date);

        $this->assertCount(1, $slots);
        $this->assertSame(2, $slots->first()['available_coach_count']);
    }

    // Google CalendarのBusy予定と重複する予約枠を除外することを確認する
    public function test_excludes_google_calendar_busy_intervals(): void
    {
        $certification = Certification::factory()->published()->create();
        $coach = User::factory()->coach()->create();
        $this->attachCoach($certification, $coach);

        $date = Carbon::parse('2026-06-01');
        CoachAvailability::factory()
            ->forCoach($coach)
            ->onDay(1)
            ->timeRange('09:00:00', '12:00:00')
            ->create();

        $credential = GoogleCredential::create([
            'user_id' => $coach->id,
            'calendar_id' => 'primary',
            'access_token' => 'access-token',
            'refresh_token' => 'refresh-token',
            'token_expires_at' => now()->addHour(),
            'connected_at' => now(),
        ]);

        $googleCalendarService = $this->mock(GoogleCalendarService::class);

        $googleCalendarService
            ->shouldReceive('busyIntervals')
            ->once()
            ->andReturn([
                [
                    'start' => $date->copy()->setTime(10, 30),
                    'end' => $date->copy()->setTime(11, 30),
                ],
            ]);

        $slots = app(MeetingAvailabilityService::class)->slotsForCertification(
            $certification,
            $date,
        );

        $times = $slots->map(fn (array $slot) => $slot['slot_start']->format('H:i'))->all();

        $this->assertEquals(['09:00'], $times);
    }

    // Google CalendarにBusy予定がない場合、予約枠を維持することを確認する
    public function test_keeps_slots_when_google_calendar_is_free(): void
    {
        $certification = Certification::factory()->published()->create();
        $coach = User::factory()->coach()->create();
        $this->attachCoach($certification, $coach);

        $date = Carbon::parse('2026-06-01');
        CoachAvailability::factory()
            ->forCoach($coach)
            ->onDay(1)
            ->timeRange('09:00:00', '12:00:00')
            ->create();

        GoogleCredential::create([
            'user_id' => $coach->id,
            'calendar_id' => 'primary',
            'access_token' => 'access-token',
            'refresh_token' => 'refresh-token',
            'token_expires_at' => now()->addHour(),
            'connected_at' => now(),
        ]);

        $googleCalendarService = $this->mock(GoogleCalendarService::class);

        $googleCalendarService
            ->shouldReceive('busyIntervals')
            ->once()
            ->andReturn([]);

        $slots = app(MeetingAvailabilityService::class)->slotsForCertification(
            $certification,
            $date,
        );

        $times = $slots->map(fn (array $slot) => $slot['slot_start']->format('H:i'))->all();

        $this->assertEquals(['09:00', '10:00', '11:00'], $times);
    }

    // Google Calendar未連携のコーチについて、LMSの予約可能枠を維持することを確認する
    public function test_keeps_slots_when_coach_is_not_connected_to_google_calendar(): void
    {
        $certification = Certification::factory()->published()->create();
        $coach = User::factory()->coach()->create();
        $this->attachCoach($certification, $coach);

        $date = Carbon::parse('2026-06-01');
        CoachAvailability::factory()
            ->forCoach($coach)
            ->onDay(1)
            ->timeRange('09:00:00', '12:00:00')
            ->create();

        $googleCalendarService = $this->mock(GoogleCalendarService::class);

        $googleCalendarService
            ->shouldNotReceive('busyIntervals');

        $slots = app(MeetingAvailabilityService::class)->slotsForCertification(
            $certification,
            $date,
        );

        $times = $slots->map(fn (array $slot) => $slot['slot_start']->format('H:i'))->all();

        $this->assertEquals(['09:00', '10:00', '11:00'], $times);
    }

    // Busy予定の開始時刻と面談枠の終了時刻が一致する場合、面談枠を維持することを確認する
    public function test_keeps_slot_ending_when_google_calendar_busy_interval_starts(): void
    {
        $certification = Certification::factory()->published()->create();
        $coach = User::factory()->coach()->create();
        $this->attachCoach($certification, $coach);

        $date = Carbon::parse('2026-06-01');

        CoachAvailability::factory()
            ->forCoach($coach)
            ->onDay(1)
            ->timeRange('09:00:00', '12:00:00')
            ->create();

        GoogleCredential::create([
            'user_id' => $coach->id,
            'calendar_id' => 'primary',
            'access_token' => 'access-token',
            'refresh_token' => 'refresh-token',
            'token_expires_at' => now()->addHour(),
            'connected_at' => now(),
        ]);

        $googleCalendarService = $this->mock(GoogleCalendarService::class);

        $googleCalendarService
            ->shouldReceive('busyIntervals')
            ->once()
            ->andReturn([
                [
                    'start' => $date->copy()->setTime(10, 0),
                    'end' => $date->copy()->setTime(11, 0),
                ],
            ]);

        $slots = app(MeetingAvailabilityService::class)->slotsForCertification(
            $certification,
            $date,
        );

        $times = $slots
            ->map(fn (array $slot) => $slot['slot_start']->format('H:i'))
            ->all();

        $this->assertEquals(['09:00', '11:00'], $times);
    }

    // Google Calendar APIでエラーが発生した場合、LMSの予約可能枠にフォールバックすることを確認する
    public function test_falls_back_to_lms_availability_when_google_calendar_fails(): void
    {
        $certification = Certification::factory()->published()->create();
        $coach = User::factory()->coach()->create();
        $this->attachCoach($certification, $coach);

        $date = Carbon::parse('2026-06-01');
        CoachAvailability::factory()
            ->forCoach($coach)
            ->onDay(1)
            ->timeRange('09:00:00', '12:00:00')
            ->create();

        GoogleCredential::create([
            'user_id' => $coach->id,
            'calendar_id' => 'primary',
            'access_token' => 'access-token',
            'refresh_token' => 'refresh-token',
            'token_expires_at' => now()->addHour(),
            'connected_at' => now(),
        ]);

        $googleCalendarService = $this->mock(GoogleCalendarService::class);

        $googleCalendarService
            ->shouldReceive('busyIntervals')
            ->once()
            ->andThrow(new \RuntimeException('Google Calendar API error'));
        $slots = app(MeetingAvailabilityService::class)->slotsForCertification(
            $certification,
            $date,
        );

        $times = $slots->map(fn (array $slot) => $slot['slot_start']->format('H:i'))->all();

        $this->assertEquals(['09:00', '10:00', '11:00'], $times);
    }

    // 面談可能時間の範囲外の予約枠を指定すると例外が発生することを確認する
    public function test_validate_slot_throws_when_outside_availability(): void
    {
        $certification = Certification::factory()->published()->create();
        $coach = User::factory()->coach()->create();
        $this->attachCoach($certification, $coach);
        CoachAvailability::factory()->forCoach($coach)->onDay(1)->timeRange('09:00:00', '10:00:00')->create();

        $this->expectException(MeetingOutOfAvailabilityException::class);

        // 月曜の枠は 09-10 のみ。15:00 は枠外
        app(MeetingAvailabilityService::class)->validateSlot($certification, Carbon::parse('2026-06-01 15:00:00'));
    }

    // 面談可能時間の範囲内の予約枠を指定すると例外が発生しないことを確認する
    public function test_validate_slot_succeeds_when_in_availability(): void
    {
        $certification = Certification::factory()->published()->create();
        $coach = User::factory()->coach()->create();
        $this->attachCoach($certification, $coach);
        CoachAvailability::factory()->forCoach($coach)->onDay(1)->timeRange('09:00:00', '12:00:00')->create();

        // 例外が起きないことを確認
        app(MeetingAvailabilityService::class)->validateSlot($certification, Carbon::parse('2026-06-01 09:00:00'));
        $this->addToAssertionCount(1);
    }
}
