<?php

declare(strict_types=1);

namespace App\Http\Requests\EnrollmentNote;

use App\Models\Enrollment;
use App\Models\EnrollmentNote;
use Illuminate\Foundation\Http\FormRequest;

class StoreRequest extends FormRequest
{
    /**
     * このリクエストを実行できるか判定する。
     */
    public function authorize(): bool
    {
        $enrollment = $this->route('enrollment');

        return $enrollment instanceof Enrollment
            && $this->user()?->can(
                'create',
                [EnrollmentNote::class, $enrollment]
            ) === true;
    }

    /**
     * バリデーションルールを取得する。
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:2000'],
        ];
    }

    /**
     * バリデーション属性名を取得する。
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'body' => 'メモ',
        ];
    }

    /**
     * バリデーションメッセージを取得する。
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'body.required' => ':attributeは必須です。',
            'body.string' => ':attributeは文字列で入力してください。',
            'body.max' => ':attributeは2000文字以内で入力してください。',
        ];
    }
}
