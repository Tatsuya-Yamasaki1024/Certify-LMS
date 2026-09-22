<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;

/**
 * プロフィール更新のバリデーション。
 */
class UpdateProfileRequest extends FormRequest
{
    /**
     * リクエストを許可する。
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * バリデーションルールを返す。
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:50'],
            'bio' => ['nullable', 'string', 'max:1000'],
        ];

        if ($this->user()?->role === UserRole::Coach) {
            $rules['meeting_url'] = ['nullable', 'string', 'url', 'max:500'];
        }

        return $rules;
    }

    /**
     * バリデーションメッセージを返す。
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => '名前を入力してください。',
            'name.string' => '名前は文字列で入力してください。',
            'name.max' => '名前は50文字以内で入力してください。',
            'bio.string' => '自己紹介は文字列で入力してください。',
            'bio.max' => '自己紹介は1000文字以内で入力してください。',
            'meeting_url.string' => '面談URLは文字列で入力してください。',
            'meeting_url.url' => '有効なURLを入力してください。',
            'meeting_url.max' => '面談URLは500文字以内で入力してください。',
        ];
    }

    /**
     * バリデーション属性名を返す。
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => '名前',
            'bio' => '自己紹介',
            'meeting_url' => '面談URL',
        ];
    }
}
