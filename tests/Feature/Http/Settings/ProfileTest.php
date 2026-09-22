<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Settings;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    // 未認証ユーザーはログイン画面へリダイレクトされることを確認する。
    public function test_unauthenticated_request_is_redirected_to_login(): void
    {
        $response = $this->get(route('settings.profile.edit'));

        $response->assertRedirect('/login');
    }

    // 生徒はプロフィールを更新できることを確認する。
    public function test_student_can_update_profile(): void
    {
        $student = User::factory()->student()->inProgress()->create([
            'name' => '変更前',
            'bio' => '変更前の自己紹介',
        ]);

        $response = $this->actingAs($student)
            ->patch(route('settings.profile.update'), [
                'name' => '変更後',
                'bio' => '変更後の自己紹介',
            ]);

        $response->assertRedirect(route('settings.profile.edit'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('users', [
            'id' => $student->id,
            'name' => '変更後',
            'bio' => '変更後の自己紹介',
        ]);
    }

    // コーチはプロフィールと固定面談URLを更新できることを確認する。
    public function test_coach_can_update_profile_and_meeting_url(): void
    {
        $coach = User::factory()->coach()->inProgress()->create([
            'name' => '変更前',
            'bio' => '変更前の自己紹介',
            'meeting_url' => 'https://example.com/old',
        ]);

        $response = $this->actingAs($coach)
            ->patch(route('settings.profile.update'), [
                'name' => '変更後',
                'bio' => '変更後の自己紹介',
                'meeting_url' => 'https://example.com/new',
            ]);

        $response->assertRedirect(route('settings.profile.edit'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('users', [
            'id' => $coach->id,
            'name' => '変更後',
            'bio' => '変更後の自己紹介',
            'meeting_url' => 'https://example.com/new',
        ]);
    }

    // コーチは固定面談URLを空欄にできることを確認する。
    public function test_coach_can_clear_meeting_url(): void
    {
        $coach = User::factory()->coach()->inProgress()->create([
            'meeting_url' => 'https://example.com/meeting',
        ]);

        $this->actingAs($coach)
            ->patch(route('settings.profile.update'), [
                'name' => $coach->name,
                'bio' => $coach->bio,
                'meeting_url' => '',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('users', [
            'id' => $coach->id,
            'meeting_url' => null,
        ]);
    }

    // 生徒は固定面談URLを更新できないことを確認する。
    public function test_student_cannot_update_meeting_url(): void
    {
        $student = User::factory()->student()->inProgress()->create([
            'meeting_url' => null,
        ]);

        $this->actingAs($student)
            ->patch(route('settings.profile.update'), [
                'name' => '変更後',
                'bio' => '変更後の自己紹介',
                'meeting_url' => 'https://example.com/meeting',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('users', [
            'id' => $student->id,
            'name' => '変更後',
            'meeting_url' => null,
        ]);
    }

    // 管理者はプロフィールを更新できることを確認する。
    public function test_admin_can_update_profile(): void
    {
        $admin = User::factory()->admin()->create([
            'name' => '変更前',
            'bio' => '変更前の自己紹介',
        ]);

        $this->actingAs($admin)
            ->patch(route('settings.profile.update'), [
                'name' => '変更後',
                'bio' => '変更後の自己紹介',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('users', [
            'id' => $admin->id,
            'name' => '変更後',
            'bio' => '変更後の自己紹介',
        ]);
    }

    // 卒業生はプロフィールを更新できることを確認する。
    public function test_graduated_student_can_update_profile(): void
    {
        $student = User::factory()->student()->graduated()->create([
            'name' => '変更前',
            'bio' => '変更前の自己紹介',
        ]);

        $this->actingAs($student)
            ->patch(route('settings.profile.update'), [
                'name' => '変更後',
                'bio' => '変更後の自己紹介',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('users', [
            'id' => $student->id,
            'name' => '変更後',
            'bio' => '変更後の自己紹介',
        ]);
    }

    // 生徒はアバター画像をアップロードできることを確認する。
    public function test_student_can_upload_avatar(): void
    {
        Storage::fake('public');

        $student = User::factory()->student()->inProgress()->create([
            'avatar_url' => null,
        ]);

        $file = UploadedFile::fake()->image('avatar.png');

        $response = $this->actingAs($student)
            ->post(route('settings.avatar.store'), [
                'avatar' => $file,
            ]);

        $response->assertRedirect(route('settings.profile.edit'));
        $response->assertSessionHas('success');

        $student->refresh();

        $this->assertNotNull($student->avatar_url);

        $path = parse_url($student->avatar_url, PHP_URL_PATH);
        $path = substr($path, strlen('/storage/'));

        Storage::disk('public')->assertExists($path);
    }

    // 生徒は設定済みのアバター画像を削除できることを確認する。
    public function test_student_can_delete_avatar(): void
    {
        Storage::fake('public');

        $student = User::factory()->student()->inProgress()->create();

        $file = UploadedFile::fake()->image('avatar.png');
        $path = 'avatars/test-avatar.png';

        Storage::disk('public')->putFileAs(
            'avatars',
            $file,
            'test-avatar.png',
        );

        $student->update([
            'avatar_url' => Storage::disk('public')->url($path),
        ]);

        $response = $this->actingAs($student)
            ->delete(route('settings.avatar.destroy'));

        $response->assertRedirect(route('settings.profile.edit'));
        $response->assertSessionHas('success');

        $student->refresh();

        $this->assertNull($student->avatar_url);
        Storage::disk('public')->assertMissing($path);
    }

    // 対応していない形式のアバター画像はアップロードできないことを確認する。
    public function test_avatar_with_invalid_format_cannot_be_uploaded(): void
    {
        Storage::fake('public');

        $student = User::factory()->student()->inProgress()->create([
            'avatar_url' => null,
        ]);

        $file = UploadedFile::fake()->create(
            'avatar.txt',
            100,
            'text/plain',
        );

        $response = $this->actingAs($student)
            ->post(route('settings.avatar.store'), [
                'avatar' => $file,
            ]);

        $response->assertSessionHasErrors('avatar');

        $student->refresh();

        $this->assertNull($student->avatar_url);
    }

    // 2MBを超えるアバター画像はアップロードできないことを確認する。
    public function test_avatar_over_2mb_cannot_be_uploaded(): void
    {
        Storage::fake('public');

        $student = User::factory()->student()->inProgress()->create([
            'avatar_url' => null,
        ]);

        $file = UploadedFile::fake()->create(
            'avatar.png',
            2049,
            'image/png',
        );

        $response = $this->actingAs($student)
            ->post(route('settings.avatar.store'), [
                'avatar' => $file,
            ]);

        $response->assertSessionHasErrors('avatar');

        $student->refresh();

        $this->assertNull($student->avatar_url);
    }

    // 正しい現在のパスワードを入力するとパスワードを変更できることを確認する。
    public function test_user_can_update_password(): void
    {
        $user = User::factory()->student()->inProgress()->create([
            'password' => 'old-password',
        ]);

        $response = $this->actingAs($user)
            ->put(route('settings.password.update'), [
                'current_password' => 'old-password',
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ]);

        $response->assertRedirect(
            route('settings.profile.edit', ['tab' => 'password'])
        );
        $response->assertSessionHas('success');

        $user->refresh();

        $this->assertTrue(
            Hash::check('new-password', $user->password)
        );
        $this->assertFalse(
            Hash::check('old-password', $user->password)
        );
    }

    // 現在のパスワードが間違っている場合はパスワードを変更できないことを確認する。
    public function test_user_cannot_update_password_with_incorrect_current_password(): void
    {
        $user = User::factory()->student()->inProgress()->create([
            'password' => 'old-password',
        ]);

        $response = $this->actingAs($user)
            ->put(route('settings.password.update'), [
                'current_password' => 'incorrect-password',
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ]);

        $response->assertSessionHasErrors(
            ['current_password'],
            null,
            'updatePassword'
        );

        $user->refresh();

        $this->assertTrue(
            Hash::check('old-password', $user->password)
        );
    }

    // 新しいパスワードと確認用パスワードが一致しない場合は変更できないことを確認する。
    public function test_user_cannot_update_password_when_confirmation_does_not_match(): void
    {
        $user = User::factory()->student()->inProgress()->create([
            'password' => 'old-password',
        ]);

        $response = $this->actingAs($user)
            ->put(route('settings.password.update'), [
                'current_password' => 'old-password',
                'password' => 'new-password',
                'password_confirmation' => 'different-password',
            ]);

        $response->assertSessionHasErrors(
            ['password'],
            null,
            'updatePassword'
        );

        $user->refresh();

        $this->assertTrue(
            Hash::check('old-password', $user->password)
        );
    }
}
