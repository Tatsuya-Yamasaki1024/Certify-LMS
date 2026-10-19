<?php

declare(strict_types=1);

namespace Tests\Feature\Http\MeetingQuota;

use App\Enums\MeetingQuotaTransactionType;
use App\Enums\PaymentStatus;
use App\Models\MeetingPack;
use App\Models\MeetingQuotaTransaction;
use App\Models\User;
use App\Services\StripeCheckoutSessionCreator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Stripe\Checkout\Session;
use Tests\TestCase;

class CheckoutTest extends TestCase
{
    use RefreshDatabase;

    // 公開中の面談パックを購入すると保留中の決済を作成してStripeへ遷移する
    public function test_student_can_start_checkout_for_published_meeting_pack(): void
    {
        $student = User::factory()->student()->create();
        $meetingPack = MeetingPack::factory()
            ->published()
            ->withCount(5)
            ->withPrice(12000)
            ->create();

        $this->mock(StripeCheckoutSessionCreator::class, function (MockInterface $mock): void {
            $mock->shouldReceive('create')
                ->once()
                ->andReturn(Session::constructFrom([
                    'id' => 'cs_test_checkout',
                    'url' => 'https://checkout.stripe.com/c/pay/cs_test_checkout',
                ]));
        });

        $response = $this->actingAs($student)->post(
            route('meeting-quota.checkout.create'),
            ['meeting_pack_id' => $meetingPack->id],
        );

        $response->assertRedirect(
            'https://checkout.stripe.com/c/pay/cs_test_checkout',
        );

        $this->assertDatabaseHas('payments', [
            'user_id' => $student->id,
            'meeting_pack_id' => $meetingPack->id,
            'status' => PaymentStatus::Pending->value,
            'amount' => 12000,
            'quantity' => 1,
            'stripe_checkout_session_id' => 'cs_test_checkout',
        ]);

        $this->assertSame(
            0,
            MeetingQuotaTransaction::query()
                ->where('user_id', $student->id)
                ->where('type', MeetingQuotaTransactionType::Purchased->value)
                ->count(),
        );
    }

    // 下書きの面談パックは購入できない
    public function test_student_cannot_checkout_draft_meeting_pack(): void
    {
        $student = User::factory()->student()->create();
        $meetingPack = MeetingPack::factory()->draft()->create();

        $this->mock(StripeCheckoutSessionCreator::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('create');
        });

        $response = $this->actingAs($student)->post(
            route('meeting-quota.checkout.create'),
            ['meeting_pack_id' => $meetingPack->id],
        );

        $response->assertRedirect(route('meeting-quota.checkout.index'));
        $response->assertSessionHas('error');

        $this->assertDatabaseCount('payments', 0);
    }

    // アーカイブ済みの面談パックは購入できない
    public function test_student_cannot_checkout_archived_meeting_pack(): void
    {
        $student = User::factory()->student()->create();
        $meetingPack = MeetingPack::factory()->archived()->create();

        $this->mock(StripeCheckoutSessionCreator::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('create');
        });

        $response = $this->actingAs($student)->post(
            route('meeting-quota.checkout.create'),
            ['meeting_pack_id' => $meetingPack->id],
        );

        $response->assertRedirect(route('meeting-quota.checkout.index'));
        $response->assertSessionHas('error');

        $this->assertDatabaseCount('payments', 0);
    }

    // Stripeのセッション作成に失敗した場合は決済を失敗扱いにする
    public function test_checkout_failure_marks_payment_failed(): void
    {
        $student = User::factory()->student()->create();
        $meetingPack = MeetingPack::factory()->published()->create();

        $this->mock(StripeCheckoutSessionCreator::class, function (MockInterface $mock): void {
            $mock->shouldReceive('create')
                ->once()
                ->andThrow(new \RuntimeException('Stripe API error.'));
        });

        $response = $this->actingAs($student)->post(
            route('meeting-quota.checkout.create'),
            ['meeting_pack_id' => $meetingPack->id],
        );

        $response->assertRedirect(route('meeting-quota.checkout.index'));
        $response->assertSessionHas('error');

        $this->assertDatabaseHas('payments', [
            'user_id' => $student->id,
            'meeting_pack_id' => $meetingPack->id,
            'status' => PaymentStatus::Failed->value,
        ]);

        $this->assertDatabaseCount('meeting_quota_transactions', 0);
    }

    // 未ログインのユーザーは購入処理を実行できない
    public function test_guest_cannot_start_checkout(): void
    {
        $meetingPack = MeetingPack::factory()->published()->create();

        $response = $this->post(
            route('meeting-quota.checkout.create'),
            ['meeting_pack_id' => $meetingPack->id],
        );

        $response->assertRedirect(route('login'));
        $this->assertDatabaseCount('payments', 0);
    }

    // 購入画面には公開中の面談パックだけが表示される
    public function test_checkout_page_shows_only_published_meeting_packs(): void
    {
        $student = User::factory()->student()->create();

        $publishedPack = MeetingPack::factory()
            ->published()
            ->create(['name' => '購入可能パック']);

        MeetingPack::factory()->draft()->create([
            'name' => '下書きパック',
        ]);

        MeetingPack::factory()->archived()->create([
            'name' => 'アーカイブ済みパック',
        ]);

        $response = $this->actingAs($student)
            ->get(route('meeting-quota.checkout.index'));

        $response->assertOk();
        $response->assertSee('購入可能パック');
        $response->assertDontSee('下書きパック');
        $response->assertDontSee('アーカイブ済みパック');
    }
}
