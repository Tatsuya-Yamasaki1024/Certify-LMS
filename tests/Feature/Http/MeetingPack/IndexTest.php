<?php

declare(strict_types=1);

namespace Tests\Feature\Http\MeetingPack;

use App\Models\MeetingPack;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IndexTest extends TestCase
{
    use RefreshDatabase;

    // 管理者は面談パック一覧を表示できる
    public function test_admin_can_view_meeting_pack_index(): void
    {
        $admin = User::factory()->admin()->create();
        MeetingPack::factory()->count(3)->create();

        $response = $this->actingAs($admin)
            ->get(route('admin.meeting-packs.index'));

        $response->assertOk();
        $response->assertViewIs('meeting-pack.management.index');
    }

    // パック名で面談パックを検索できる
    public function test_meeting_packs_can_be_searched_by_name(): void
    {
        $admin = User::factory()->admin()->create();

        $target = MeetingPack::factory()->create([
            'name' => 'プレミアム面談パック',
        ]);

        $other = MeetingPack::factory()->create([
            'name' => '通常面談パック',
        ]);

        $response = $this->actingAs($admin)
            ->get(route('admin.meeting-packs.index', [
                'keyword' => 'プレミアム',
            ]));

        $response->assertOk();
        $response->assertSee($target->name);
        $response->assertDontSee($other->name);
    }

    // description は検索対象にならない
    public function test_meeting_packs_are_not_searched_by_description(): void
    {
        $admin = User::factory()->admin()->create();

        $meetingPack = MeetingPack::factory()->create([
            'name' => '通常面談パック',
            'description' => 'プレミアムという説明文',
        ]);

        $response = $this->actingAs($admin)
            ->get(route('admin.meeting-packs.index', [
                'keyword' => 'プレミアム',
            ]));

        $response->assertOk();
        $response->assertDontSee($meetingPack->name);
    }

    // ステータスで面談パックを絞り込める
    public function test_meeting_packs_can_be_filtered_by_status(): void
    {
        $admin = User::factory()->admin()->create();

        $published = MeetingPack::factory()
            ->published()
            ->create([
                'name' => '公開中パック',
            ]);

        $draft = MeetingPack::factory()
            ->draft()
            ->create([
                'name' => '下書きパック',
            ]);

        $response = $this->actingAs($admin)
            ->get(route('admin.meeting-packs.index', [
                'status' => 'published',
            ]));

        $response->assertOk();
        $response->assertSee($published->name);
        $response->assertDontSee($draft->name);
    }

    // 公開中の面談パックが先に表示される
    public function test_published_meeting_packs_are_displayed_first(): void
    {
        $admin = User::factory()->admin()->create();

        $draft = MeetingPack::factory()
            ->draft()
            ->create([
                'name' => '下書きパック',
                'sort_order' => 1,
            ]);

        $published = MeetingPack::factory()
            ->published()
            ->create([
                'name' => '公開中パック',
                'sort_order' => 99,
            ]);

        $response = $this->actingAs($admin)
            ->get(route('admin.meeting-packs.index'));

        $response->assertOk();

        $content = $response->getContent();

        $this->assertLessThan(
            strpos($content, $draft->name),
            strpos($content, $published->name),
        );
    }

    // 公開中以外の面談パックはsort_order順に表示される
    public function test_non_published_meeting_packs_are_ordered_by_sort_order(): void
    {
        $admin = User::factory()->admin()->create();

        $high = MeetingPack::factory()
            ->draft()
            ->create([
                'name' => 'sort_order 20',
                'sort_order' => 20,
            ]);

        $low = MeetingPack::factory()
            ->draft()
            ->create([
                'name' => 'sort_order 10',
                'sort_order' => 10,
            ]);

        $response = $this->actingAs($admin)
            ->get(route('admin.meeting-packs.index'));

        $response->assertOk();

        $content = $response->getContent();

        $this->assertLessThan(
            strpos($content, $high->name),
            strpos($content, $low->name),
        );
    }

    // コーチは面談パック一覧を表示できない
    public function test_coach_cannot_view_meeting_pack_index(): void
    {
        $coach = User::factory()->coach()->create();

        $response = $this->actingAs($coach)
            ->get(route('admin.meeting-packs.index'));

        $response->assertForbidden();
    }

    // 学生は面談パック一覧を表示できない
    public function test_student_cannot_view_meeting_pack_index(): void
    {
        $student = User::factory()->student()->create();

        $response = $this->actingAs($student)
            ->get(route('admin.meeting-packs.index'));

        $response->assertForbidden();
    }
}
