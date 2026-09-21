<?php

declare(strict_types=1);

namespace App\Http\Requests\EnrollmentGoal;

use App\Models\EnrollmentGoal;
use Illuminate\Foundation\Http\FormRequest;

class UpdateRequest extends FormRequest
{
    /**
     * このリクエストを実行できるか判定する。
     */
    public function authorize(): bool
    {
        $goal = $this->route('goal');

        return $goal instanceof EnrollmentGoal
            && $this->user()?->can('update', $goal) === true;
    }

    /**
     * バリデーションルールを取得する。
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:1000'],
            'target_date' => ['nullable', 'date'],
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
            'title' => '目標',
            'description' => '説明',
            'target_date' => '目標期日',
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
            'title.required' => ':attributeは必須です。',
            'title.string' => ':attributeは文字列で入力してください。',
            'title.max' => ':attributeは100文字以内で入力してください。',

            'description.string' => ':attributeは文字列で入力してください。',
            'description.max' => ':attributeは1000文字以内で入力してください。',

            'target_date.date' => ':attributeは正しい日付を入力してください。',
        ];
    }
}
