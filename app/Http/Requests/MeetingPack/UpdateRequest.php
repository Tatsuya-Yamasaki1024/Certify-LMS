<?php

declare(strict_types=1);

namespace App\Http\Requests\MeetingPack;

use App\Models\MeetingPack;
use Illuminate\Foundation\Http\FormRequest;

class UpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        $meetingPack = $this->route('plan');

        return $meetingPack instanceof MeetingPack
            && $this->user()?->can('update', $meetingPack) === true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:2000'],
            'meeting_count' => ['required', 'integer', 'min:1', 'max:100'],
            'price' => ['required', 'integer', 'min:0', 'max:1000000'],
            'stripe_price_id' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'SKU名',
            'description' => '説明',
            'meeting_count' => '面談回数',
            'price' => '価格',
            'stripe_price_id' => 'Stripe Price ID',
            'sort_order' => '表示順',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => ':attributeを入力してください。',
            'name.string' => ':attributeは文字列で入力してください。',
            'name.max' => ':attributeは100文字以内で入力してください。',

            'description.string' => ':attributeは文字列で入力してください。',
            'description.max' => ':attributeは2000文字以内で入力してください。',

            'meeting_count.required' => ':attributeを入力してください。',
            'meeting_count.integer' => ':attributeは整数で入力してください。',
            'meeting_count.min' => ':attributeは1以上で入力してください。',
            'meeting_count.max' => ':attributeは100以下で入力してください。',

            'price.required' => ':attributeを入力してください。',
            'price.integer' => ':attributeは整数で入力してください。',
            'price.min' => ':attributeは0以上で入力してください。',
            'price.max' => ':attributeは1,000,000以下で入力してください。',

            'stripe_price_id.string' => ':attributeは文字列で入力してください。',
            'stripe_price_id.max' => ':attributeは255文字以内で入力してください。',

            'sort_order.integer' => ':attributeは整数で入力してください。',
            'sort_order.min' => ':attributeは0以上で入力してください。',
        ];
    }
}
