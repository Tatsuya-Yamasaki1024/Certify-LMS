<?php

declare(strict_types=1);

namespace Tests\Feature\Http\QaBoard;

use App\Enums\QaThreadStatus;
use App\Models\Certification;
use App\Models\QaReply;
use App\Models\QaThread;
use App\Models\User;
use Database\Factories\CertificationCoachAssignmentFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IndexTest extends TestCase
{
    use RefreshDatabase;

    // キーワード検索で質問タイトルから検索できる
    public function test_keyword_search_finds_thread_by_title(): void
    {
        $student = User::factory()->student()->create();
        $certification = Certification::factory()->published()->create();

        QaThread::factory()->create([
            'user_id' => $student->id,
            'certification_id' => $certification->id,
            'title' => 'Laravelについての質問',
            'body' => 'これは質問本文です。',
        ]);

        $this->actingAs($student)
            ->get(route('qa-board.index', ['keyword' => 'Laravel']))
            ->assertOk()
            ->assertSee('Laravelについての質問');
    }

    // キーワード検索で質問本文から検索できる
    public function test_keyword_search_finds_thread_by_body(): void
    {
        $student = User::factory()->student()->create();
        $certification = Certification::factory()->published()->create();

        QaThread::factory()->create([
            'user_id' => $student->id,
            'certification_id' => $certification->id,
            'title' => '質問タイトル',
            'body' => 'Laravelの検索機能について質問します。',
        ]);

        $this->actingAs($student)
            ->get(route('qa-board.index', ['keyword' => 'Laravel']))
            ->assertOk()
            ->assertSee('質問タイトル');
    }

    // キーワード検索で回答本文から検索できる
    public function test_keyword_search_finds_thread_by_reply_body(): void
    {
        $student = User::factory()->student()->create();
        $certification = Certification::factory()->published()->create();

        $thread = QaThread::factory()->create([
            'user_id' => $student->id,
            'certification_id' => $certification->id,
            'title' => '質問タイトル',
            'body' => '質問本文です。',
        ]);

        QaReply::factory()->create([
            'qa_thread_id' => $thread->id,
            'user_id' => $student->id,
            'body' => 'Laravelについて回答します。',
        ]);

        $this->actingAs($student)
            ->get(route('qa-board.index', ['keyword' => 'Laravel']))
            ->assertOk()
            ->assertSee('質問タイトル');
    }

    // 受講生は公開済み資格すべてのスレッドを閲覧できる
    public function test_student_can_view_threads_from_all_published_certifications(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $certificationA = Certification::factory()->published()->create();
        $certificationB = Certification::factory()->published()->create();

        $threadA = QaThread::factory()->create([
            'user_id' => $student->id,
            'certification_id' => $certificationA->id,
            'title' => '資格Aの質問',
        ]);

        $threadB = QaThread::factory()->create([
            'user_id' => $student->id,
            'certification_id' => $certificationB->id,
            'title' => '資格Bの質問',
        ]);

        $this->actingAs($student)
            ->get(route('qa-board.index'))
            ->assertOk()
            ->assertSee($threadA->title)
            ->assertSee($threadB->title);
    }

    // 質問一覧は新しい質問から表示される
    public function test_student_can_view_threads_in_newest_order(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();

        $olderThread = QaThread::factory()->create([
            'user_id' => $student->id,
            'certification_id' => $certification->id,
            'title' => '古い質問',
            'created_at' => now()->subMinutes(10),
        ]);

        $newerThread = QaThread::factory()->create([
            'user_id' => $student->id,
            'certification_id' => $certification->id,
            'title' => '新しい質問',
            'created_at' => now(),
        ]);

        $response = $this->actingAs($student)
            ->get(route('qa-board.index'))
            ->assertOk();

        $response->assertSeeInOrder([
            $newerThread->title,
            $olderThread->title,
        ]);
    }

    // 質問一覧は20件ずつページネーションされる
    public function test_student_can_view_qa_threads_with_20_items_per_page(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();

        QaThread::factory()
            ->count(21)
            ->create([
                'user_id' => $student->id,
                'certification_id' => $certification->id,
            ]);

        $firstPageResponse = $this->actingAs($student)
            ->get(route('qa-board.index'))
            ->assertOk();

        $this->assertCount(
            20,
            $firstPageResponse->viewData('threads')
        );

        $secondPageResponse = $this->actingAs($student)
            ->get(route('qa-board.index', ['page' => 2]))
            ->assertOk();

        $this->assertCount(
            1,
            $secondPageResponse->viewData('threads')
        );
    }

    // 受講生は公開済み資格を選択して質問スレッドを新規投稿できる
    public function test_student_can_create_qa_thread(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();

        $response = $this->actingAs($student)->post(
            route('qa-board.store'),
            [
                'certification_id' => $certification->id,
                'title' => '質問タイトル',
                'body' => '質問本文です。',
            ]
        );

        $thread = QaThread::query()->latest('created_at')->first();

        $response->assertRedirect(route('qa-board.show', $thread));

        $this->assertDatabaseHas('qa_threads', [
            'id' => $thread->id,
            'user_id' => $student->id,
            'certification_id' => $certification->id,
            'title' => '質問タイトル',
            'body' => '質問本文です。',
        ]);
    }

    // 受講生は投稿者本人の質問スレッドを編集できる
    public function test_student_can_update_own_qa_thread(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();

        $thread = QaThread::factory()->create([
            'user_id' => $student->id,
            'certification_id' => $certification->id,
            'title' => '変更前のタイトル',
            'body' => '変更前の本文です。',
        ]);

        $response = $this->actingAs($student)->patch(
            route('qa-board.update', $thread),
            [
                'title' => '変更後のタイトル',
                'body' => '変更後の本文です。',
            ]
        );

        $response->assertRedirect(route('qa-board.show', $thread));

        $this->assertDatabaseHas('qa_threads', [
            'id' => $thread->id,
            'user_id' => $student->id,
            'certification_id' => $certification->id,
            'title' => '変更後のタイトル',
            'body' => '変更後の本文です。',
        ]);
    }

    // 受講生は投稿者本人の質問スレッドを編集しても資格を変更できない
    public function test_student_cannot_change_certification_when_updating_own_qa_thread(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $originalCertification = Certification::factory()->published()->create();
        $otherCertification = Certification::factory()->published()->create();

        $thread = QaThread::factory()->create([
            'user_id' => $student->id,
            'certification_id' => $originalCertification->id,
            'title' => '変更前のタイトル',
            'body' => '変更前の本文です。',
        ]);

        $response = $this->actingAs($student)->patch(
            route('qa-board.update', $thread),
            [
                'certification_id' => $otherCertification->id,
                'title' => '変更後のタイトル',
                'body' => '変更後の本文です。',
            ]
        );

        $response->assertRedirect(route('qa-board.show', $thread));

        $this->assertDatabaseHas('qa_threads', [
            'id' => $thread->id,
            'certification_id' => $originalCertification->id,
            'title' => '変更後のタイトル',
            'body' => '変更後の本文です。',
        ]);

        $this->assertDatabaseMissing('qa_threads', [
            'id' => $thread->id,
            'certification_id' => $otherCertification->id,
        ]);
    }

    // 受講生は他人の質問スレッドに回答を投稿できる
    public function test_student_can_reply_to_another_students_qa_thread(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $threadOwner = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();

        $thread = QaThread::factory()->create([
            'user_id' => $threadOwner->id,
            'certification_id' => $certification->id,
        ]);

        $response = $this->actingAs($student)->post(
            route('qa-board.replies.store', $thread),
            [
                'body' => '回答本文です。',
            ]
        );

        $response->assertRedirect();

        $this->assertDatabaseHas('qa_replies', [
            'qa_thread_id' => $thread->id,
            'user_id' => $student->id,
            'body' => '回答本文です。',
        ]);
    }

    // 受講生は自分の回答を編集できる
    public function test_student_can_update_own_qa_reply(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $threadOwner = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();

        $thread = QaThread::factory()->create([
            'user_id' => $threadOwner->id,
            'certification_id' => $certification->id,
        ]);

        $reply = QaReply::factory()->create([
            'qa_thread_id' => $thread->id,
            'user_id' => $student->id,
        ]);

        $response = $this->actingAs($student)->patch(
            route('qa-board.replies.update', [$thread, $reply]),
            [
                'body' => '編集後の回答内容',
            ],
        );

        $response->assertRedirect();

        $this->assertDatabaseHas('qa_replies', [
            'id' => $reply->id,
            'body' => '編集後の回答内容',
        ]);
    }

    // 受講生は他人の回答を編集できない
    public function test_student_cannot_update_another_students_qa_reply(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $otherStudent = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();

        $thread = QaThread::factory()->create([
            'user_id' => $otherStudent->id,
            'certification_id' => $certification->id,
        ]);

        $reply = QaReply::factory()->create([
            'qa_thread_id' => $thread->id,
            'user_id' => $otherStudent->id,
        ]);

        $response = $this->actingAs($student)->patch(
            route('qa-board.replies.update', [$thread, $reply]),
            [
                'body' => '他人の回答を編集しようとする',
            ]
        );

        $response->assertForbidden();

        $this->assertDatabaseHas('qa_replies', [
            'id' => $reply->id,
            'body' => $reply->body,
        ]);
    }

    // 受講生は自分の回答を削除できる
    public function test_student_can_delete_own_qa_reply(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $threadOwner = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();

        $thread = QaThread::factory()->create([
            'user_id' => $threadOwner->id,
            'certification_id' => $certification->id,
        ]);

        $reply = QaReply::factory()->create([
            'qa_thread_id' => $thread->id,
            'user_id' => $student->id,
        ]);

        $response = $this->actingAs($student)->delete(
            route('qa-board.replies.destroy', [$thread, $reply])
        );

        $response->assertRedirect();

        $this->assertDatabaseMissing('qa_replies', [
            'id' => $reply->id,
        ]);
    }

    // 受講生は他人の回答を削除できない
    public function test_student_cannot_delete_another_students_qa_reply(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $otherStudent = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();

        $thread = QaThread::factory()->create([
            'user_id' => $otherStudent->id,
            'certification_id' => $certification->id,
        ]);

        $reply = QaReply::factory()->create([
            'qa_thread_id' => $thread->id,
            'user_id' => $otherStudent->id,
        ]);

        $response = $this->actingAs($student)->delete(
            route('qa-board.replies.destroy', [$thread, $reply])
        );

        $response->assertForbidden();

        $this->assertDatabaseHas('qa_replies', [
            'id' => $reply->id,
            'user_id' => $otherStudent->id,
        ]);
    }

    // 受講生は投稿者本人の質問スレッドを解決済みに変更できる
    public function test_student_can_resolve_own_qa_thread(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();

        $thread = QaThread::factory()->create([
            'user_id' => $student->id,
            'certification_id' => $certification->id,
            'status' => 'unresolved',
            'resolved_at' => null,
        ]);

        $response = $this->actingAs($student)->post(
            route('qa-board.resolve', $thread)
        );

        $response->assertRedirect();

        $this->assertDatabaseHas('qa_threads', [
            'id' => $thread->id,
            'status' => 'resolved',
        ]);

        $this->assertNotNull(
            QaThread::query()->findOrFail($thread->id)->resolved_at
        );
    }

    // 受講生は投稿者本人の質問スレッドを未解決に変更できる
    public function test_student_can_unresolve_own_qa_thread(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();

        $thread = QaThread::factory()->create([
            'user_id' => $student->id,
            'certification_id' => $certification->id,
            'status' => 'resolved',
            'resolved_at' => now(),
        ]);

        $response = $this->actingAs($student)->post(
            route('qa-board.unresolve', $thread)
        );

        $response->assertRedirect();

        $this->assertDatabaseHas('qa_threads', [
            'id' => $thread->id,
            'status' => 'unresolved',
            'resolved_at' => null,
        ]);
    }

    // 受講生は回答がない投稿者本人の質問スレッドを削除できる
    public function test_student_can_delete_own_qa_thread_without_replies(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();

        $thread = QaThread::factory()->create([
            'user_id' => $student->id,
            'certification_id' => $certification->id,
        ]);

        $response = $this->actingAs($student)->delete(
            route('qa-board.destroy', $thread)
        );

        $response->assertRedirect(route('qa-board.index'));

        $this->assertDatabaseMissing('qa_threads', [
            'id' => $thread->id,
        ]);
    }

    // 受講生は回答がある投稿者本人の質問スレッドを削除できない
    public function test_student_cannot_delete_own_qa_thread_with_replies(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $replyUser = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();

        $thread = QaThread::factory()->create([
            'user_id' => $student->id,
            'certification_id' => $certification->id,
        ]);

        QaReply::factory()->create([
            'qa_thread_id' => $thread->id,
            'user_id' => $replyUser->id,
        ]);

        $this->actingAs($student)
            ->delete(route('qa-board.destroy', $thread))
            ->assertForbidden();

        $this->assertDatabaseHas('qa_threads', [
            'id' => $thread->id,
        ]);
    }

    // 受講生は他人の質問を編集できない
    public function test_student_cannot_update_another_students_qa_thread(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $otherStudent = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();

        $thread = QaThread::factory()->create([
            'user_id' => $otherStudent->id,
            'certification_id' => $certification->id,
        ]);

        $response = $this->actingAs($student)
            ->patch(route('qa-board.update', $thread), [
                'title' => '更新後のタイトル',
                'body' => '更新後の質問内容',
            ]);

        $response->assertForbidden();

        $this->assertDatabaseHas('qa_threads', [
            'id' => $thread->id,
            'title' => $thread->title,
            'body' => $thread->body,
        ]);
    }

    // 受講生は他人の質問を削除できない（回答なし）
    public function test_student_cannot_delete_another_students_qa_thread_without_replies(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $otherStudent = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();

        $thread = QaThread::factory()->create([
            'user_id' => $otherStudent->id,
            'certification_id' => $certification->id,
        ]);

        $response = $this->actingAs($student)
            ->delete(route('qa-board.destroy', $thread));

        $response->assertForbidden();

        $this->assertDatabaseHas('qa_threads', [
            'id' => $thread->id,
        ]);
    }

    // 受講生は他人の質問を解決済みにできない
    public function test_student_cannot_resolve_another_students_qa_thread(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $otherStudent = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();

        $thread = QaThread::factory()->create([
            'user_id' => $otherStudent->id,
            'certification_id' => $certification->id,
            'status' => QaThreadStatus::Unresolved,
        ]);

        $response = $this->actingAs($student)
            ->post(route('qa-board.resolve', $thread));

        $response->assertForbidden();

        $thread->refresh();

        $this->assertSame(QaThreadStatus::Unresolved, $thread->status);
    }

    // 受講生は他人の質問を未解決に戻せない
    public function test_student_cannot_unresolve_another_students_qa_thread(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $otherStudent = User::factory()->student()->inProgress()->create();

        $certification = Certification::factory()->published()->create();

        $thread = QaThread::factory()->create([
            'user_id' => $otherStudent->id,
            'certification_id' => $certification->id,
            'status' => QaThreadStatus::Resolved,
            'resolved_at' => now(),
        ]);

        $response = $this->actingAs($student)
            ->post(route('qa-board.unresolve', $thread));

        $response->assertForbidden();

        $thread->refresh();

        $this->assertSame(QaThreadStatus::Resolved, $thread->status);
    }

    // コーチは担当資格のスレッドを閲覧できる
    public function test_coach_can_view_thread_for_assigned_certification(): void
    {
        $coach = User::factory()->coach()->inProgress()->create();
        $student = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();

        CertificationCoachAssignmentFactory::new()->create([
            'certification_id' => $certification->id,
            'user_id' => $coach->id,
        ]);

        $thread = QaThread::factory()->create([
            'user_id' => $student->id,
            'certification_id' => $certification->id,
            'title' => '担当資格の質問',
        ]);

        $this->actingAs($coach)
            ->get(route('qa-board.show', $thread))
            ->assertOk()
            ->assertSee($thread->title);
    }

    // コーチは担当資格のスレッドに回答できる
    public function test_coach_can_reply_to_thread_for_assigned_certification(): void
    {
        $coach = User::factory()->coach()->inProgress()->create();
        $student = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();

        CertificationCoachAssignmentFactory::new()->create([
            'certification_id' => $certification->id,
            'user_id' => $coach->id,
        ]);

        $thread = QaThread::factory()->create([
            'user_id' => $student->id,
            'certification_id' => $certification->id,
        ]);

        $response = $this->actingAs($coach)->post(
            route('qa-board.replies.store', $thread),
            [
                'body' => '回答本文です。',
            ]
        );

        $response->assertRedirect();

        $this->assertDatabaseHas('qa_replies', [
            'qa_thread_id' => $thread->id,
            'user_id' => $coach->id,
            'body' => '回答本文です。',
        ]);
    }

    // コーチは担当外資格のスレッドを閲覧できない
    public function test_coach_cannot_view_thread_for_unassigned_certification(): void
    {
        $coach = User::factory()->coach()->inProgress()->create();
        $certification = Certification::factory()->published()->create();
        $student = User::factory()->student()->inProgress()->create();

        $thread = QaThread::factory()->create([
            'user_id' => $student->id,
            'certification_id' => $certification->id,
        ]);

        $this->actingAs($coach)
            ->get(route('qa-board.show', $thread))
            ->assertForbidden();
    }

    // コーチは担当外資格のスレッドに回答できない
    public function test_coach_cannot_reply_to_thread_for_unassigned_certification(): void
    {
        $coach = User::factory()->coach()->inProgress()->create();
        $certification = Certification::factory()->published()->create();
        $student = User::factory()->student()->inProgress()->create();

        $thread = QaThread::factory()->create([
            'user_id' => $student->id,
            'certification_id' => $certification->id,
        ]);

        $this->actingAs($coach)
            ->post(
                route('qa-board.replies.store', $thread),
                ['body' => '回答本文です。']
            )
            ->assertForbidden();

        $this->assertDatabaseMissing('qa_replies', [
            'qa_thread_id' => $thread->id,
            'user_id' => $coach->id,
        ]);
    }

    // コーチは質問を投稿できない
    public function test_coach_cannot_create_qa_thread(): void
    {
        $coach = User::factory()->coach()->inProgress()->create();
        $certification = Certification::factory()->published()->create();

        CertificationCoachAssignmentFactory::new()->create([
            'certification_id' => $certification->id,
            'user_id' => $coach->id,
        ]);

        $response = $this->actingAs($coach)
            ->post(route('qa-board.store'), [
                'certification_id' => $certification->id,
                'title' => 'コーチからの質問',
                'body' => '質問本文',
            ]);

        $response->assertForbidden();

        $this->assertDatabaseMissing('qa_threads', [
            'user_id' => $coach->id,
            'title' => 'コーチからの質問',
        ]);
    }

    // 受講生は公開停止中の資格のスレッドを閲覧できない
    public function test_student_cannot_view_thread_for_unpublished_certification(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->archived()->create();

        $thread = QaThread::factory()->create([
            'user_id' => $student->id,
            'certification_id' => $certification->id,
        ]);

        $this->actingAs($student)
            ->get(route('qa-board.show', $thread))
            ->assertForbidden();
    }

    // コーチは公開停止中の資格のスレッドを閲覧できない
    public function test_coach_cannot_view_thread_for_unpublished_certification(): void
    {
        $coach = User::factory()->coach()->inProgress()->create();
        $certification = Certification::factory()->archived()->create();
        $student = User::factory()->student()->inProgress()->create();

        CertificationCoachAssignmentFactory::new()->create([
            'certification_id' => $certification->id,
            'user_id' => $coach->id,
        ]);

        $thread = QaThread::factory()->create([
            'user_id' => $student->id,
            'certification_id' => $certification->id,
        ]);

        $this->actingAs($coach)
            ->get(route('qa-board.show', $thread))
            ->assertForbidden();
    }

    // 管理者は公開停止中の資格のスレッドを閲覧できる
    public function test_admin_can_view_thread_for_unpublished_certification(): void
    {
        $admin = User::factory()->admin()->create();
        $student = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->archived()->create();

        $thread = QaThread::factory()->create([
            'user_id' => $student->id,
            'certification_id' => $certification->id,
            'title' => '公開停止資格の質問',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.qa-board.show', $thread))
            ->assertOk()
            ->assertSee($thread->title);
    }

    // 管理者は公開停止中の資格を含む全資格のスレッドを閲覧できる
    public function test_admin_can_view_threads_from_all_certifications_including_unpublished_certifications(): void
    {
        $admin = User::factory()->admin()->create();

        $publishedCertification = Certification::factory()->published()->create();
        $archivedCertification = Certification::factory()->archived()->create();

        $student = User::factory()->student()->inProgress()->create();

        $publishedThread = QaThread::factory()->create([
            'user_id' => $student->id,
            'certification_id' => $publishedCertification->id,
        ]);

        $archivedThread = QaThread::factory()->create([
            'user_id' => $student->id,
            'certification_id' => $archivedCertification->id,
        ]);

        $response = $this->actingAs($admin)
            ->get(route('admin.qa-board.index'));

        $response->assertOk()
            ->assertSee($publishedThread->title)
            ->assertSee($archivedThread->title);
    }

    // 管理者は任意のスレッドを削除できる
    public function test_admin_can_delete_any_qa_thread(): void
    {
        $admin = User::factory()->admin()->create();
        $student = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();

        $thread = QaThread::factory()->create([
            'user_id' => $student->id,
            'certification_id' => $certification->id,
        ]);

        $reply = QaReply::factory()->create([
            'qa_thread_id' => $thread->id,
            'user_id' => $student->id,
        ]);

        $response = $this->actingAs($admin)
            ->delete(route('admin.qa-board.destroy', $thread));

        $response->assertRedirect();

        $this->assertDatabaseMissing('qa_threads', [
            'id' => $thread->id,
        ]);

        $this->assertDatabaseMissing('qa_replies', [
            'id' => $reply->id,
        ]);
    }

    // 管理者は任意の回答を削除できる
    public function test_admin_can_delete_any_qa_reply(): void
    {
        $admin = User::factory()->admin()->create();
        $student = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();

        $thread = QaThread::factory()->create([
            'user_id' => $student->id,
            'certification_id' => $certification->id,
        ]);

        $reply = QaReply::factory()->create([
            'qa_thread_id' => $thread->id,
            'user_id' => $student->id,
        ]);

        $response = $this->actingAs($admin)
            ->delete(route('admin.qa-board.replies.destroy', [$thread, $reply]));

        $response->assertRedirect();

        $this->assertDatabaseMissing('qa_replies', [
            'id' => $reply->id,
        ]);

        $this->assertDatabaseHas('qa_threads', [
            'id' => $thread->id,
        ]);
    }

    // 管理者は質問を投稿できない
    public function test_admin_cannot_create_qa_thread(): void
    {
        $admin = User::factory()->admin()->create();
        $certification = Certification::factory()->published()->create();

        $response = $this->actingAs($admin)
            ->post(route('qa-board.store'), [
                'certification_id' => $certification->id,
                'title' => '管理者からの質問',
                'body' => '質問本文',
            ]);

        $response->assertForbidden();

        $this->assertDatabaseMissing('qa_threads', [
            'user_id' => $admin->id,
            'title' => '管理者からの質問',
        ]);
    }

    // 管理者は質問を編集できない
    public function test_admin_cannot_update_qa_thread(): void
    {
        $admin = User::factory()->admin()->create();
        $student = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();

        $thread = QaThread::factory()->create([
            'user_id' => $student->id,
            'certification_id' => $certification->id,
        ]);

        $response = $this->actingAs($admin)
            ->patch(route('qa-board.update', $thread), [
                'title' => '管理者による更新',
                'body' => '更新された本文',
            ]);

        $response->assertForbidden();

        $this->assertDatabaseHas('qa_threads', [
            'id' => $thread->id,
            'title' => $thread->title,
            'body' => $thread->body,
        ]);
    }

    // 管理者は回答を編集できない
    public function test_admin_cannot_update_qa_reply(): void
    {
        $admin = User::factory()->admin()->create();
        $student = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();

        $thread = QaThread::factory()->create([
            'user_id' => $student->id,
            'certification_id' => $certification->id,
        ]);

        $reply = QaReply::factory()->create([
            'qa_thread_id' => $thread->id,
            'user_id' => $student->id,
        ]);

        $response = $this->actingAs($admin)
            ->patch(route('qa-board.replies.update', [$thread, $reply]), [
                'body' => '管理者による回答編集',
            ]);

        $response->assertForbidden();

        $this->assertDatabaseHas('qa_replies', [
            'id' => $reply->id,
            'body' => $reply->body,
        ]);
    }

    // 管理者は解決マークを代行できない
    public function test_admin_cannot_resolve_qa_thread(): void
    {
        $admin = User::factory()->admin()->create();
        $student = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();

        $thread = QaThread::factory()->create([
            'user_id' => $student->id,
            'certification_id' => $certification->id,
            'status' => QaThreadStatus::Unresolved,
        ]);

        $response = $this->actingAs($admin)
            ->post(route('qa-board.resolve', $thread));

        $response->assertForbidden();

        $thread->refresh();

        $this->assertSame(QaThreadStatus::Unresolved, $thread->status);
    }

    // ⑧ ユーザーステータスによるアクセス制御

    // 卒業した受講生は質問掲示板にアクセスできない
    public function test_graduated_student_cannot_access_qa_board(): void
    {
        $student = User::factory()->student()->graduated()->create();

        $this->actingAs($student)
            ->get(route('qa-board.index'))
            ->assertForbidden();
    }

    // 退会済みの受講生は質問掲示板にアクセスできない
    public function test_withdrawn_student_cannot_access_qa_board(): void
    {
        $student = User::factory()->student()->withdrawn()->create();

        $this->actingAs($student)
            ->get(route('qa-board.index'))
            ->assertForbidden();
    }

    // 退会済みの受講生は質問を投稿できない
    public function test_withdrawn_student_cannot_post_to_qa_board(): void
    {
        $student = User::factory()->student()->withdrawn()->create();
        $certification = Certification::factory()->published()->create();

        $response = $this->actingAs($student)
            ->post(route('qa-board.store'), [
                'certification_id' => $certification->id,
                'title' => '退会済みユーザーからの質問',
                'body' => '質問本文です。',
            ]);

        $response->assertForbidden();

        $this->assertDatabaseMissing('qa_threads', [
            'user_id' => $student->id,
            'title' => '退会済みユーザーからの質問',
        ]);
    }

    // 退会済みの受講生は回答を投稿できない
    public function test_withdrawn_student_cannot_reply_to_qa_thread(): void
    {
        $student = User::factory()->student()->withdrawn()->create();
        $threadOwner = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();

        $thread = QaThread::factory()->create([
            'user_id' => $threadOwner->id,
            'certification_id' => $certification->id,
        ]);

        $response = $this->actingAs($student)
            ->post(
                route('qa-board.replies.store', $thread),
                ['body' => '退会済みユーザーからの回答']
            );

        $response->assertForbidden();

        $this->assertDatabaseMissing('qa_replies', [
            'qa_thread_id' => $thread->id,
            'user_id' => $student->id,
        ]);
    }
}
