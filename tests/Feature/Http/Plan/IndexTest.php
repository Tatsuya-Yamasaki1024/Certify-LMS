<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Plan;

use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IndexTest extends TestCase
{
    use RefreshDatabase;

    // 管理者はプラン一覧を表示できる
    public function test_admin_can_view_plan_index(): void
    {
        $admin = User::factory()->admin()->create();
        Plan::factory()->count(3)->create();

        $response = $this->actingAs($admin)
            ->get(route('admin.plans.index'));

        $response->assertOk();
        $response->assertViewIs('plan.management.index');
    }

    // プラン名でプランを検索できる
    public function test_plans_can_be_searched_by_name(): void
    {
        $admin = User::factory()->admin()->create();

        $target = Plan::factory()->create([
            'name' => 'プレミアムプラン',
        ]);

        $other = Plan::factory()->create([
            'name' => '通常プラン',
        ]);

        $response = $this->actingAs($admin)
            ->get(route('admin.plans.index', [
                'keyword' => 'プレミアム',
            ]));

        $response->assertOk();
        $response->assertSee($target->name);
        $response->assertDontSee($other->name);
    }

    // ステータスでプランを絞り込める
    public function test_plans_can_be_filtered_by_status(): void
    {
        $admin = User::factory()->admin()->create();

        $published = Plan::factory()
            ->published()
            ->create([
                'name' => '公開中プラン',
            ]);

        $draft = Plan::factory()
            ->draft()
            ->create([
                'name' => '下書きプラン',
            ]);

        $response = $this->actingAs($admin)
            ->get(route('admin.plans.index', [
                'status' => 'published',
            ]));

        $response->assertOk();
        $response->assertSee($published->name);
        $response->assertDontSee($draft->name);
    }

    // 公開中のプランが先に表示される
    public function test_published_plans_are_displayed_first(): void
    {
        $admin = User::factory()->admin()->create();

        $draft = Plan::factory()
            ->draft()
            ->create([
                'name' => '下書きプラン',
                'sort_order' => 1,
            ]);

        $published = Plan::factory()
            ->published()
            ->create([
                'name' => '公開中プラン',
                'sort_order' => 99,
            ]);

        $response = $this->actingAs($admin)
            ->get(route('admin.plans.index'));

        $response->assertOk();

        $content = $response->getContent();

        $this->assertLessThan(
            strpos($content, $draft->name),
            strpos($content, $published->name),
        );
    }

    // 公開中以外のプランはsort_order順に表示される
    public function test_non_published_plans_are_ordered_by_sort_order(): void
    {
        $admin = User::factory()->admin()->create();

        $high = Plan::factory()
            ->draft()
            ->create([
                'name' => 'sort_order 20',
                'sort_order' => 20,
            ]);

        $low = Plan::factory()
            ->draft()
            ->create([
                'name' => 'sort_order 10',
                'sort_order' => 10,
            ]);

        $response = $this->actingAs($admin)
            ->get(route('admin.plans.index'));

        $response->assertOk();

        $content = $response->getContent();

        $this->assertLessThan(
            strpos($content, $high->name),
            strpos($content, $low->name),
        );
    }

    // コーチはプラン一覧を表示できない
    public function test_coach_cannot_view_plan_index(): void
    {
        $coach = User::factory()->coach()->create();

        $response = $this->actingAs($coach)
            ->get(route('admin.plans.index'));

        $response->assertForbidden();
    }

    // 学生はプラン一覧を表示できない
    public function test_student_cannot_view_plan_index(): void
    {
        $student = User::factory()->student()->create();

        $response = $this->actingAs($student)
            ->get(route('admin.plans.index'));

        $response->assertForbidden();
    }
}
