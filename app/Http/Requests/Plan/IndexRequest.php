<?php

declare(strict_types=1);

namespace App\Http\Requests\Plan;

use App\Enums\PlanStatus;
use App\Models\Plan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Plan::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'keyword' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::enum(PlanStatus::class)],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function attributes(): array
    {
        return [
            'keyword' => 'キーワード',
            'status' => 'ステータス',
            'page' => 'ページ番号',
        ];
    }

    public function messages(): array
    {
        return [
            'keyword.string' => ':attributeは文字列で入力してください。',
            'keyword.max' => ':attributeは100文字以内で入力してください。',

            'status.enum' => '選択された:attributeは正しくありません。',

            'page.integer' => ':attributeは整数で指定してください。',
            'page.min' => ':attributeは1以上で指定してください。',
        ];
    }
}
