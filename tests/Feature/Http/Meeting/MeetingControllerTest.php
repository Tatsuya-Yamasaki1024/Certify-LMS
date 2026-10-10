<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Meeting;

use App\Enums\MeetingQuotaTransactionType;
use App\Enums\MeetingStatus;
use App\Models\Certification;
use App\Models\CoachAvailability;
use App\Models\Enrollment;
use App\Models\GoogleCredential;
use App\Models\Meeting;
use App\Models\User;
use App\Services\GoogleCalendarService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class MeetingControllerTest extends TestCase
{
    use RefreshDatabase;

    private function attachCoach(Certification $certification, User $coach, User $admin): void
    {
        $certification->coaches()->attach($coach->id, [
            'id' => (string) Str::ulid(),
            'assigned_by_user_id' => $admin->id,
            'assigned_at' => now(),
            'unassigned_at' => null,
        ]);
    }

    // 受講生の面談一覧には本人の面談だけが表示されることを確認する
    public function test_student_index_lists_only_own_meetings(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $otherStudent = User::factory()->student()->inProgress()->create();
        $coach = User::factory()->coach()->create();
        $own = Meeting::factory()->reserved()->forCoach($coach)->forStudent($student)->create([
            'scheduled_at' => now()->addDays(3)->startOfHour(),
        ]);
        $other = Meeting::factory()->reserved()->forCoach($coach)->forStudent($otherStudent)->create([
            'scheduled_at' => now()->addDays(4)->startOfHour(),
        ]);

        $response = $this->actingAs($student)->get(route('meetings.index'));

        $response->assertOk();
        $response->assertViewIs('meeting.index');
        $response->assertViewHas('meetings', fn ($meetings) => $meetings->contains('id', $own->id)
            && ! $meetings->contains('id', $other->id));
    }

    // 他の受講生の面談詳細にはアクセスできないことを確認する
    public function test_show_blocks_third_party(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $thirdParty = User::factory()->student()->inProgress()->create();
        $coach = User::factory()->coach()->create();
        $meeting = Meeting::factory()->reserved()->forCoach($coach)->forStudent($student)->create();

        $response = $this->actingAs($thirdParty)->get(route('meetings.show', $meeting));

        $response->assertForbidden();
    }

    // 面談の受講生本人と担当コーチが面談詳細にアクセスできることを確認する
    public function test_show_allows_owner(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $coach = User::factory()->coach()->create();
        $meeting = Meeting::factory()->reserved()->forCoach($coach)->forStudent($student)->create();

        $this->actingAs($student)->get(route('meetings.show', $meeting))->assertOk();
        $this->actingAs($coach)->get(route('meetings.show', $meeting))->assertOk();
    }

    // 他の受講生が所有する受講登録では面談予約画面を表示できないことを確認する
    public function test_create_requires_enrollment_ownership(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $other = User::factory()->student()->inProgress()->create();
        $coach = User::factory()->coach()->create();
        $admin = User::factory()->admin()->create();
        $certification = Certification::factory()->published()->create();
        $this->attachCoach($certification, $coach, $admin);
        $foreignEnrollment = Enrollment::factory()->for($other, 'user')->for($certification)->learning()->create();

        $response = $this->actingAs($student)->get(route('meetings.create', $foreignEnrollment));

        $response->assertForbidden();
    }

    // 受講生本人が予約可能な日時を指定すると面談が作成されることを確認する
    public function test_store_creates_meeting_for_owner(): void
    {
        $student = User::factory()->student()->inProgress()->create(['max_meetings' => 3]);
        $admin = User::factory()->admin()->create();
        $coach = User::factory()->coach()->inProgress()->create([
            'meeting_url' => 'https://meet.example.com/coach-room',
        ]);
        $certification = Certification::factory()->published()->create();
        $this->attachCoach($certification, $coach, $admin);
        CoachAvailability::factory()->forCoach($coach)->onDay(1)->timeRange('09:00:00', '18:00:00')->create();

        $enrollment = Enrollment::factory()->for($student, 'user')->for($certification)->learning()->create();
        $scheduledAt = now()->startOfDay()->next(Carbon::MONDAY)->setTime(10, 0); // 次の月曜 10:00(未来)

        $response = $this->actingAs($student)->post(route('meetings.store', $enrollment), [
            'scheduled_at' => $scheduledAt->format('Y-m-d\TH:i:s'),
            'topic' => '相談したい',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('meetings', [
            'student_id' => $student->id,
            'coach_id' => $coach->id,
            'enrollment_id' => $enrollment->id,
            'status' => MeetingStatus::Reserved->value,
        ]);
    }

    // Google Calendar連携済みのコーチに面談を予約するとGoogle Calendarの予定も作成されることを確認する
    public function test_store_creates_google_calendar_event_for_connected_coach(): void
    {
        $student = User::factory()->student()->inProgress()->create(['max_meetings' => 3]);
        $admin = User::factory()->admin()->create();
        $coach = User::factory()->coach()->inProgress()->create([
            'meeting_url' => 'https://meet.example.com/coach-room',
        ]);
        $certification = Certification::factory()->published()->create();
        $this->attachCoach($certification, $coach, $admin);

        CoachAvailability::factory()
            ->forCoach($coach)
            ->onDay(1)
            ->timeRange('09:00:00', '18:00:00')
            ->create();

        $credential = GoogleCredential::create([
            'user_id' => $coach->id,
            'calendar_id' => 'primary',
            'access_token' => 'access-token',
            'refresh_token' => 'refresh-token',
            'token_expires_at' => now()->addHour(),
            'connected_at' => now(),
        ]);

        $enrollment = Enrollment::factory()
            ->for($student, 'user')
            ->for($certification)
            ->learning()
            ->create();

        $scheduledAt = now()->startOfDay()->next(Carbon::MONDAY)->setTime(10, 0);

        $googleCalendarService = $this->mock(GoogleCalendarService::class);

        $googleCalendarService
            ->shouldReceive('busyIntervals')
            ->twice()
            ->andReturn([]);

        $googleCalendarService
            ->shouldReceive('createEvent')
            ->once()
            ->andReturn('google-event-123');

        $response = $this->actingAs($student)->post(route('meetings.store', $enrollment), [
            'scheduled_at' => $scheduledAt->format('Y-m-d\TH:i:s'),
            'topic' => '相談したい',
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('meetings', [
            'student_id' => $student->id,
            'coach_id' => $coach->id,
            'enrollment_id' => $enrollment->id,
            'status' => MeetingStatus::Reserved->value,
            'google_event_id' => 'google-event-123',
        ]);
    }

    // Google Calendarへの予定登録が失敗してもLMSの面談予約は成立し、GoogleイベントIDがNULLになることを確認する
    public function test_store_succeeds_when_google_calendar_event_creation_fails(): void
    {
        $student = User::factory()->student()->inProgress()->create(['max_meetings' => 3]);
        $admin = User::factory()->admin()->create();
        $coach = User::factory()->coach()->inProgress()->create([
            'meeting_url' => 'https://meet.example.com/coach-room',
        ]);
        $certification = Certification::factory()->published()->create();
        $this->attachCoach($certification, $coach, $admin);

        CoachAvailability::factory()
            ->forCoach($coach)
            ->onDay(1)
            ->timeRange('09:00:00', '18:00:00')
            ->create();

        GoogleCredential::create([
            'user_id' => $coach->id,
            'calendar_id' => 'primary',
            'access_token' => 'access-token',
            'refresh_token' => 'refresh-token',
            'token_expires_at' => now()->addHour(),
            'connected_at' => now(),
        ]);

        $enrollment = Enrollment::factory()
            ->for($student, 'user')
            ->for($certification)
            ->learning()
            ->create();

        $scheduledAt = now()->startOfDay()->next(Carbon::MONDAY)->setTime(10, 0);

        $googleCalendarService = $this->mock(GoogleCalendarService::class);

        $googleCalendarService
            ->shouldReceive('busyIntervals')
            ->twice()
            ->andReturn([]);

        $googleCalendarService
            ->shouldReceive('createEvent')
            ->once()
            ->andThrow(new \RuntimeException('Google Calendar API error.'));

        $response = $this->actingAs($student)->post(route('meetings.store', $enrollment), [
            'scheduled_at' => $scheduledAt->format('Y-m-d\TH:i:s'),
            'topic' => '相談したい',
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('meetings', [
            'student_id' => $student->id,
            'coach_id' => $coach->id,
            'enrollment_id' => $enrollment->id,
            'status' => MeetingStatus::Reserved->value,
            'google_event_id' => null,
        ]);
    }

    // Google CalendarのBusy予定と重なる日時ではコーチを割り当てず、面談予約を作成しないことを確認する
    public function test_store_does_not_assign_coach_when_google_calendar_is_busy(): void
    {
        $student = User::factory()->student()->inProgress()->create(['max_meetings' => 3]);
        $admin = User::factory()->admin()->create();
        $coach = User::factory()->coach()->inProgress()->create([
            'meeting_url' => 'https://meet.example.com/coach-room',
        ]);
        $certification = Certification::factory()->published()->create();
        $this->attachCoach($certification, $coach, $admin);

        CoachAvailability::factory()
            ->forCoach($coach)
            ->onDay(1)
            ->timeRange('09:00:00', '18:00:00')
            ->create();

        GoogleCredential::create([
            'user_id' => $coach->id,
            'calendar_id' => 'primary',
            'access_token' => 'access-token',
            'refresh_token' => 'refresh-token',
            'token_expires_at' => now()->addHour(),
            'connected_at' => now(),
        ]);

        $enrollment = Enrollment::factory()
            ->for($student, 'user')
            ->for($certification)
            ->learning()
            ->create();

        $scheduledAt = now()->startOfDay()->next(Carbon::MONDAY)->setTime(10, 0);

        $googleCalendarService = $this->mock(GoogleCalendarService::class);

        $googleCalendarService
            ->shouldReceive('busyIntervals')
            ->andReturn([
                [
                    'start' => $scheduledAt->copy()->addMinutes(30),
                    'end' => $scheduledAt->copy()->addMinutes(90),
                ],
            ]);

        $response = $this->actingAs($student)->post(route('meetings.store', $enrollment), [
            'scheduled_at' => $scheduledAt->format('Y-m-d\TH:i:s'),
            'topic' => '相談したい',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseCount('meetings', 0);
    }

    // あるコーチがGoogle CalendarのBusy予定で予約できない場合、別の空いているコーチが割り当てられることを確認する
    public function test_store_assigns_available_coach_when_another_coach_is_google_busy(): void
    {
        $student = User::factory()->student()->inProgress()->create(['max_meetings' => 3]);
        $admin = User::factory()->admin()->create();

        $busyCoach = User::factory()->coach()->inProgress()->create([
            'meeting_url' => 'https://meet.example.com/busy-coach',
        ]);
        $availableCoach = User::factory()->coach()->inProgress()->create([
            'meeting_url' => 'https://meet.example.com/available-coach',
        ]);

        $certification = Certification::factory()->published()->create();
        $this->attachCoach($certification, $busyCoach, $admin);
        $this->attachCoach($certification, $availableCoach, $admin);

        CoachAvailability::factory()
            ->forCoach($busyCoach)
            ->onDay(1)
            ->timeRange('09:00:00', '18:00:00')
            ->create();

        CoachAvailability::factory()
            ->forCoach($availableCoach)
            ->onDay(1)
            ->timeRange('09:00:00', '18:00:00')
            ->create();

        GoogleCredential::create([
            'user_id' => $busyCoach->id,
            'calendar_id' => 'primary',
            'access_token' => 'access-token',
            'refresh_token' => 'refresh-token',
            'token_expires_at' => now()->addHour(),
            'connected_at' => now(),
        ]);

        $enrollment = Enrollment::factory()
            ->for($student, 'user')
            ->for($certification)
            ->learning()
            ->create();

        $scheduledAt = now()->startOfDay()->next(Carbon::MONDAY)->setTime(10, 0);

        $googleCalendarService = $this->mock(GoogleCalendarService::class);

        $googleCalendarService
            ->shouldReceive('busyIntervals')
            ->twice()
            ->andReturn([
                [
                    'start' => $scheduledAt->copy()->addMinutes(30),
                    'end' => $scheduledAt->copy()->addMinutes(90),
                ],
            ]);

        $response = $this->actingAs($student)->post(route('meetings.store', $enrollment), [
            'scheduled_at' => $scheduledAt->format('Y-m-d\TH:i:s'),
            'topic' => '相談したい',
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('meetings', [
            'student_id' => $student->id,
            'coach_id' => $availableCoach->id,
            'enrollment_id' => $enrollment->id,
            'status' => MeetingStatus::Reserved->value,
        ]);

        $this->assertDatabaseMissing('meetings', [
            'student_id' => $student->id,
            'coach_id' => $busyCoach->id,
            'enrollment_id' => $enrollment->id,
        ]);
    }

    // 予約時刻の分が00以外の場合、バリデーションエラーとなり面談が作成されないことを確認する
    public function test_store_rejects_non_zero_minutes(): void
    {
        $student = User::factory()->student()->inProgress()->create(['max_meetings' => 3]);
        $admin = User::factory()->admin()->create();
        $coach = User::factory()->coach()->create();
        $certification = Certification::factory()->published()->create();
        $this->attachCoach($certification, $coach, $admin);
        CoachAvailability::factory()->forCoach($coach)->onDay(1)->timeRange('09:00:00', '18:00:00')->create();
        $enrollment = Enrollment::factory()->for($student, 'user')->for($certification)->learning()->create();

        $scheduledAt = now()->startOfDay()->next(Carbon::MONDAY)->setTime(10, 30); // 次の月曜 10:30(分が 0 でない=不正)
        $response = $this->actingAs($student)->post(route('meetings.store', $enrollment), [
            'scheduled_at' => $scheduledAt->format('Y-m-d\TH:i:s'),
            'topic' => '相談したい',
        ]);

        $response->assertSessionHasErrors('scheduled_at');
        $this->assertDatabaseCount('meetings', 0);
    }

    // 他の受講生が面談をキャンセルしようとすると拒否され、面談状態が変わらないことを確認する
    public function test_cancel_blocks_third_party(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $thirdParty = User::factory()->student()->inProgress()->create();
        $coach = User::factory()->coach()->create();
        $meeting = Meeting::factory()->reserved()->forCoach($coach)->forStudent($student)->create([
            'scheduled_at' => now()->addDays(3)->startOfHour(),
        ]);

        $response = $this->actingAs($thirdParty)->post(route('meetings.cancel', $meeting));

        $response->assertForbidden();
        $this->assertSame(MeetingStatus::Reserved, $meeting->fresh()->status);
    }

    // 面談の受講生本人が予約をキャンセルできることを確認する
    public function test_cancel_allows_owner(): void
    {
        $student = User::factory()->student()->inProgress()->create(['max_meetings' => 5]);
        $coach = User::factory()->coach()->create();
        $meeting = Meeting::factory()->reserved()->forCoach($coach)->forStudent($student)->create([
            'scheduled_at' => now()->addDays(3)->startOfHour(),
        ]);

        $response = $this->actingAs($student)->post(route('meetings.cancel', $meeting));

        $response->assertRedirect();
        $this->assertSame(MeetingStatus::Canceled, $meeting->fresh()->status);
    }

    // Google Calendar連携済みのコーチの面談をキャンセルすると、対応するGoogle Calendar予定の削除を試みることを確認する
    public function test_cancel_deletes_google_calendar_event_for_connected_coach(): void
    {
        $student = User::factory()->student()->inProgress()->create(['max_meetings' => 3]);
        $coach = User::factory()->coach()->inProgress()->create([
            'meeting_url' => 'https://meet.example.com/coach-room',
        ]);
        $certification = Certification::factory()->published()->create();
        $admin = User::factory()->admin()->create();
        $this->attachCoach($certification, $coach, $admin);

        GoogleCredential::create([
            'user_id' => $coach->id,
            'calendar_id' => 'primary',
            'access_token' => 'access-token',
            'refresh_token' => 'refresh-token',
            'token_expires_at' => now()->addHour(),
            'connected_at' => now(),
        ]);

        $enrollment = Enrollment::factory()
            ->for($student, 'user')
            ->for($certification)
            ->learning()
            ->create();

        $meeting = Meeting::factory()
            ->for($student, 'student')
            ->for($coach, 'coach')
            ->for($enrollment)
            ->reserved()
            ->create([
                'google_event_id' => 'google-event-123',
                'scheduled_at' => now()->addHour(),
            ]);

        $googleCalendarService = $this->mock(GoogleCalendarService::class);

        $googleCalendarService
            ->shouldReceive('deleteEvent')
            ->once();

        $response = $this->actingAs($student)->post(
            route('meetings.cancel', $meeting),
        );

        $response->assertRedirect();

        $this->assertDatabaseHas('meetings', [
            'id' => $meeting->id,
            'status' => MeetingStatus::Canceled->value,
            'google_event_id' => 'google-event-123',
        ]);
    }

    // Google Calendar予定の削除に失敗しても、LMSの面談キャンセルは成立することを確認する
    public function test_cancel_succeeds_when_google_calendar_event_deletion_fails(): void
    {
        $student = User::factory()->student()->inProgress()->create(['max_meetings' => 3]);
        $coach = User::factory()->coach()->inProgress()->create([
            'meeting_url' => 'https://meet.example.com/coach-room',
        ]);
        $certification = Certification::factory()->published()->create();
        $admin = User::factory()->admin()->create();
        $this->attachCoach($certification, $coach, $admin);

        GoogleCredential::create([
            'user_id' => $coach->id,
            'calendar_id' => 'primary',
            'access_token' => 'access-token',
            'refresh_token' => 'refresh-token',
            'token_expires_at' => now()->addHour(),
            'connected_at' => now(),
        ]);

        $enrollment = Enrollment::factory()
            ->for($student, 'user')
            ->for($certification)
            ->learning()
            ->create();

        $meeting = Meeting::factory()
            ->for($student, 'student')
            ->for($coach, 'coach')
            ->for($enrollment)
            ->reserved()
            ->create([
                'google_event_id' => 'google-event-123',
                'scheduled_at' => now()->addHour(),
            ]);

        $googleCalendarService = $this->mock(GoogleCalendarService::class);

        $googleCalendarService
            ->shouldReceive('deleteEvent')
            ->once()
            ->andThrow(new \RuntimeException('Google Calendar API error.'));

        $response = $this->actingAs($student)->post(
            route('meetings.cancel', $meeting),
        );

        $response->assertRedirect();

        $this->assertDatabaseHas('meetings', [
            'id' => $meeting->id,
            'status' => MeetingStatus::Canceled->value,
            'google_event_id' => 'google-event-123',
        ]);
    }

    // コーチの面談一覧には本人が担当する面談だけが表示されることを確認する
    public function test_index_as_coach_only_lists_own_meetings(): void
    {
        $coach = User::factory()->coach()->create();
        $otherCoach = User::factory()->coach()->create();
        $student = User::factory()->student()->create();
        $own = Meeting::factory()->reserved()->forCoach($coach)->forStudent($student)->create([
            'scheduled_at' => now()->addDays(3)->startOfHour(),
        ]);
        $other = Meeting::factory()->reserved()->forCoach($otherCoach)->forStudent($student)->create([
            'scheduled_at' => now()->addDays(4)->startOfHour(),
        ]);

        $response = $this->actingAs($coach)->get(route('coach.meetings.index'));

        $response->assertOk();
        $response->assertViewIs('meeting.coach.index');
        $response->assertViewHas('meetings', fn ($meetings) => $meetings->contains('id', $own->id)
            && ! $meetings->contains('id', $other->id));
    }

    // 担当外のコーチが面談メモを登録・更新できないことを確認する
    public function test_upsert_memo_only_for_assigned_coach(): void
    {
        $coach = User::factory()->coach()->create();
        $otherCoach = User::factory()->coach()->create();
        $student = User::factory()->student()->create();
        $meeting = Meeting::factory()->completed()->forCoach($coach)->forStudent($student)->create();

        $response = $this->actingAs($otherCoach)->put(route('coach.meetings.memo', $meeting), [
            'body' => '他人のメモを書く試み',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('meeting_memos', ['meeting_id' => $meeting->id]);
    }

    // 面談の担当コーチが面談メモを登録できることを確認する
    public function test_upsert_memo_succeeds_for_assigned_coach(): void
    {
        $coach = User::factory()->coach()->create();
        $student = User::factory()->student()->create();
        $meeting = Meeting::factory()->completed()->forCoach($coach)->forStudent($student)->create();

        $response = $this->actingAs($coach)->put(route('coach.meetings.memo', $meeting), [
            'body' => '初回面談メモ',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('meeting_memos', [
            'meeting_id' => $meeting->id,
            'body' => '初回面談メモ',
        ]);
    }

    // 指定した日付の面談可能枠がJSON形式で返されることを確認する
    public function test_fetch_availability_returns_json_slots(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $admin = User::factory()->admin()->create();
        $coach = User::factory()->coach()->create();
        $certification = Certification::factory()->published()->create();
        $this->attachCoach($certification, $coach, $admin);
        CoachAvailability::factory()->forCoach($coach)->onDay(1)->timeRange('09:00:00', '12:00:00')->create();
        $enrollment = Enrollment::factory()->for($student, 'user')->for($certification)->learning()->create();

        $date = now()->startOfDay()->next(Carbon::MONDAY)->format('Y-m-d'); // 次の月曜(未来)
        $response = $this->actingAs($student)->getJson(
            route('meetings.availability', $enrollment)."?date={$date}"
        );

        $response->assertOk();
        $response->assertJsonStructure([
            'date',
            'slots' => [
                '*' => ['slot_start', 'slot_end', 'available_coach_count'],
            ],
        ]);
        $this->assertCount(3, $response->json('slots'));
    }

    // 修了済みの受講生は面談予約画面にアクセスできないことを確認する
    public function test_graduated_student_cannot_access_create(): void
    {
        $student = User::factory()->student()->graduated()->create();
        $admin = User::factory()->admin()->create();
        $coach = User::factory()->coach()->create();
        $certification = Certification::factory()->published()->create();
        $this->attachCoach($certification, $coach, $admin);
        $enrollment = Enrollment::factory()->for($student, 'user')->for($certification)->learning()->create();

        $response = $this->actingAs($student)->get(route('meetings.create', $enrollment));

        $response->assertForbidden();
    }

    // 同じコーチ・同じ日時に面談が既に存在する場合、二重予約が成立しないことを確認する
    public function test_store_blocks_double_booking_for_same_coach_and_slot(): void
    {
        // Arrange: 予約可能コンテキスト + 同コーチ・同時刻に canceled 面談を 1 件先在させる。
        // canceled は候補抽出(予約済コーチ除外)をすり抜けるが、(coach_id, scheduled_at) UNIQUE は
        // status を問わず効くため、並行を起こさず決定論的に二重予約の衝突を再現できる。
        $student = User::factory()->student()->inProgress()->create(['max_meetings' => 3]);
        $otherStudent = User::factory()->student()->create();
        $admin = User::factory()->admin()->create();
        $coach = User::factory()->coach()->inProgress()->create([
            'meeting_url' => 'https://meet.example.com/coach-room',
        ]);
        $certification = Certification::factory()->published()->create();
        $this->attachCoach($certification, $coach, $admin);
        CoachAvailability::factory()->forCoach($coach)->onDay(1)->timeRange('09:00:00', '18:00:00')->create();
        $enrollment = Enrollment::factory()->for($student, 'user')->for($certification)->learning()->create();
        $scheduledAt = now()->startOfDay()->next(Carbon::MONDAY)->setTime(10, 0); // 次の月曜 10:00(未来)

        Meeting::factory()->reserved()->forCoach($coach)->forStudent($otherStudent)->create([
            'scheduled_at' => $scheduledAt,
        ]);

        // Act
        $response = $this->actingAs($student)
            ->from(route('meetings.create', $enrollment))
            ->post(route('meetings.store', $enrollment), [
                'scheduled_at' => $scheduledAt->format('Y-m-d\TH:i:s'),
                'topic' => '相談したい',
            ]);

        // Assert: 二重予約は成立せず、新規 reserved は作られない(canceled の 1 件のみが残る)
        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertSame(
            1,
            Meeting::query()->where('status', MeetingStatus::Reserved->value)->count(),
            '同コーチ・同時刻の二重予約によって新しい予約が作成されないこと',
        );
    }

    // 面談をキャンセルすると、消費済みの面談回数が返却されることを確認する
    public function test_cancel_refunds_meeting_quota(): void
    {
        // Arrange: 予約済(残数消費済)面談 1 件。キャンセルで返却記録が作られることを確認する。
        $student = User::factory()->student()->inProgress()->create(['max_meetings' => 5]);
        $coach = User::factory()->coach()->create();
        $meeting = Meeting::factory()->reserved()->forCoach($coach)->forStudent($student)->create([
            'scheduled_at' => now()->addDays(3)->startOfHour(),
        ]);

        // Act
        $response = $this->actingAs($student)->post(route('meetings.cancel', $meeting));

        // Assert: キャンセル成立 + 消費分 1 回が返却記録として作られる
        $response->assertRedirect();
        $this->assertSame(MeetingStatus::Canceled, $meeting->fresh()->status);
        $this->assertDatabaseHas('meeting_quota_transactions', [
            'user_id' => $student->id,
            'related_meeting_id' => $meeting->id,
            'type' => MeetingQuotaTransactionType::Refunded->value,
            'amount' => 1,
        ]);
    }
}
