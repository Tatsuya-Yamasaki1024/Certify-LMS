<?php

declare(strict_types=1);

namespace App\Http\Requests\Plan;

use App\Models\Plan;
use Illuminate\Foundation\Http\FormRequest;

class StoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Plan::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:2000'],
            'duration_days' => ['required', 'integer', 'min:1', 'max:3650'],
            'default_meeting_quota' => ['required', 'integer', 'min:0', 'max:1000'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'プラン名',
            'description' => '説明',
            'duration_days' => '受講期間',
            'default_meeting_quota' => '初期付与面談回数',
            'sort_order' => '表示順',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => ':attributeは必須です。',
            'name.string' => ':attributeは文字列で入力してください。',
            'name.max' => ':attributeは100文字以内で入力してください。',

            'description.string' => ':attributeは文字列で入力してください。',
            'description.max' => ':attributeは2000文字以内で入力してください。',

            'duration_days.required' => ':attributeは必須です。',
            'duration_days.integer' => ':attributeは整数で入力してください。',
            'duration_days.min' => ':attributeは1以上で入力してください。',
            'duration_days.max' => ':attributeは3650以下で入力してください。',

            'default_meeting_quota.required' => ':attributeは必須です。',
            'default_meeting_quota.integer' => ':attributeは整数で入力してください。',
            'default_meeting_quota.min' => ':attributeは0以上で入力してください。',
            'default_meeting_quota.max' => ':attributeは1000以下で入力してください。',

            'sort_order.integer' => ':attributeは整数で入力してください。',
            'sort_order.min' => ':attributeは0以上で入力してください。',
        ];
    }
}
