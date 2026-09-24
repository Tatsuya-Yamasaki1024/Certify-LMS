<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests\Announcement;

use App\Enums\AnnouncementTargetType;
use App\Models\Certification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class StoreRequestTest extends TestCase
{
    use RefreshDatabase;

    // 全受講生を配信対象にした場合、バリデーションを通過することを確認する。
    public function test_validation_passes_with_all_students_target(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();

        // Act
        $response = $this->actingAs($admin)->post(
            route('admin.announcements.store'),
            [
                'title' => '運営からのお知らせ',
                'body' => 'お知らせ本文です。',
                'target_type' => AnnouncementTargetType::AllStudents->value,
            ],
        );

        // Assert
        $response->assertSessionDoesntHaveErrors();
        $response->assertStatus(302);
    }

    // 資格指定を配信対象にした場合、対象資格を指定するとバリデーションを通過することを確認する。
    public function test_validation_passes_with_certification_target(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $certification = Certification::factory()->published()->create();

        // Act
        $response = $this->actingAs($admin)->post(
            route('admin.announcements.store'),
            [
                'title' => '資格指定のお知らせ',
                'body' => '資格指定本文です。',
                'target_type' => AnnouncementTargetType::Certification->value,
                'target_certification_id' => $certification->id,
            ],
        );

        // Assert
        $response->assertSessionDoesntHaveErrors();
        $response->assertStatus(302);
    }

    // ユーザー指定を配信対象にした場合、対象受講生を指定するとバリデーションを通過することを確認する。
    public function test_validation_passes_with_user_target(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $student = User::factory()->student()->inProgress()->create();

        // Act
        $response = $this->actingAs($admin)->post(
            route('admin.announcements.store'),
            [
                'title' => 'ユーザー指定のお知らせ',
                'body' => 'ユーザー指定本文です。',
                'target_type' => AnnouncementTargetType::User->value,
                'target_user_id' => $student->id,
            ],
        );

        // Assert
        $response->assertSessionDoesntHaveErrors();
        $response->assertStatus(302);
    }

    // 不正な入力値を指定した場合、バリデーションエラーになることを確認する。
    #[DataProvider('invalidPayloads')]
    public function test_validation_fails(
        array $overrides,
        string $expectedErrorField,
    ): void {
        // Arrange
        $admin = User::factory()->admin()->create();
        $certification = Certification::factory()->published()->create();
        $student = User::factory()->student()->inProgress()->create();

        $payload = array_merge([
            'title' => 'お知らせタイトル',
            'body' => 'お知らせ本文です。',
            'target_type' => AnnouncementTargetType::Certification->value,
            'target_certification_id' => $certification->id,
            'target_user_id' => $student->id,
        ], $overrides);

        // Act
        $response = $this->actingAs($admin)->postJson(
            route('admin.announcements.store'),
            $payload,
        );

        // Assert
        $response->assertStatus(422);
        $response->assertJsonValidationErrors($expectedErrorField);
    }

    // 管理者以外のユーザーがお知らせを配信できないことを確認する。
    public function test_authorize_returns_false_for_non_admin(): void
    {
        // Arrange
        $coach = User::factory()->coach()->create();
        $certification = Certification::factory()->published()->create();

        // Act
        $response = $this->actingAs($coach)->postJson(
            route('admin.announcements.store'),
            [
                'title' => 'お知らせ',
                'body' => '本文です。',
                'target_type' => AnnouncementTargetType::Certification->value,
                'target_certification_id' => $certification->id,
            ],
        );

        // Assert
        $response->assertForbidden();
    }

    /**
     * 不正な入力値と期待するバリデーションエラーフィールドを提供する。
     *
     * @return array<string, array{0: array<string, mixed>, 1: string}>
     */
    public static function invalidPayloads(): array
    {
        return [
            'title 未指定で 422' => [
                ['title' => ''],
                'title',
            ],
            'title 201 文字で 422' => [
                ['title' => str_repeat('a', 201)],
                'title',
            ],
            'body 未指定で 422' => [
                ['body' => ''],
                'body',
            ],
            'body 5001 文字で 422' => [
                ['body' => str_repeat('a', 5001)],
                'body',
            ],
            'target_type 未指定で 422' => [
                ['target_type' => ''],
                'target_type',
            ],
            'target_type 不正値で 422' => [
                ['target_type' => 'unknown'],
                'target_type',
            ],
            '資格指定で target_certification_id 未指定で 422' => [
                ['target_certification_id' => ''],
                'target_certification_id',
            ],
            '資格指定で target_certification_id 不正な ULID で 422' => [
                ['target_certification_id' => 'not-ulid'],
                'target_certification_id',
            ],
            '資格指定で target_certification_id 存在しない ULID で 422' => [
                ['target_certification_id' => (string) Str::ulid()],
                'target_certification_id',
            ],
            'ユーザー指定で target_user_id 未指定で 422' => [
                [
                    'target_type' => AnnouncementTargetType::User->value,
                    'target_certification_id' => null,
                    'target_user_id' => '',
                ],
                'target_user_id',
            ],
            'ユーザー指定で target_user_id 不正な ULID で 422' => [
                [
                    'target_type' => AnnouncementTargetType::User->value,
                    'target_certification_id' => null,
                    'target_user_id' => 'not-ulid',
                ],
                'target_user_id',
            ],
            'ユーザー指定で target_user_id 存在しない ULID で 422' => [
                [
                    'target_type' => AnnouncementTargetType::User->value,
                    'target_certification_id' => null,
                    'target_user_id' => (string) Str::ulid(),
                ],
                'target_user_id',
            ],
        ];
    }
}
