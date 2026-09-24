<?php

declare(strict_types=1);

namespace App\Http\Requests\Announcement;

use App\Enums\AnnouncementTargetType;
use App\Models\Announcement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Announcement::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'title' => [
                'required',
                'string',
                'max:200',
            ],
            'body' => [
                'required',
                'string',
                'max:5000',
            ],
            'target_type' => [
                'required',
                Rule::enum(AnnouncementTargetType::class),
            ],
            'target_certification_id' => [
                Rule::requiredIf(
                    fn () => $this->input('target_type') === AnnouncementTargetType::Certification->value
                ),
                'nullable',
                'ulid',
                Rule::exists('certifications', 'id'),
            ],
            'target_user_id' => [
                Rule::requiredIf(
                    fn () => $this->input('target_type') === AnnouncementTargetType::User->value
                ),
                'nullable',
                'ulid',
                Rule::exists('users', 'id'),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'タイトルを入力してください。',
            'title.max' => 'タイトルは200文字以内で入力してください。',
            'body.required' => '本文を入力してください。',
            'body.max' => '本文は5000文字以内で入力してください。',
            'target_type.required' => '配信対象を選択してください。',
            'target_certification_id.required' => '対象資格を選択してください。',
            'target_certification_id.ulid' => '対象資格の指定が正しくありません。',
            'target_certification_id.exists' => '選択した対象資格が存在しません。',
            'target_user_id.required' => '対象受講生を選択してください。',
            'target_user_id.ulid' => '対象受講生の指定が正しくありません。',
            'target_user_id.exists' => '選択した対象受講生が存在しません。',
        ];
    }

    public function attributes(): array
    {
        return [
            'title' => 'タイトル',
            'body' => '本文',
            'target_type' => '配信対象',
            'target_certification_id' => '対象資格',
            'target_user_id' => '対象受講生',
        ];
    }
}
