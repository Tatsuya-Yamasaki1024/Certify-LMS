<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

/**
 * アバター画像アップロードのバリデーション。
 */
class StoreAvatarRequest extends FormRequest
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
        return [
            'avatar' => [
                'required',
                'image',
                'mimes:png,jpg,jpeg,webp',
                'max:2048',
            ],
        ];
    }

    /**
     * バリデーションメッセージを返す。
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'avatar.required' => 'アバター画像を選択してください。',
            'avatar.image' => '画像ファイルを選択してください。',
            'avatar.mimes' => 'PNG、JPG、JPEG、WebP形式の画像を選択してください。',
            'avatar.max' => 'アバター画像は2MB以内で選択してください。',
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
            'avatar' => 'アバター画像',
        ];
    }
}
