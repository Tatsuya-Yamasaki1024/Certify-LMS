<?php

declare(strict_types=1);

namespace App\Http\Requests\QaBoard;

use App\Enums\CertificationStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreQaThreadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'certification_id' => [
                'required',
                Rule::exists('certifications', 'id')
                    ->where('status', CertificationStatus::Published->value),
            ],
            'title' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string', 'max:5000'],
        ];
    }

    public function messages(): array
    {
        return [
            'certification_id.required' => '資格を選択してください。',
            'certification_id.exists' => '選択した資格は利用できません。',
            'title.required' => 'タイトルを入力してください。',
            'title.max' => 'タイトルは200文字以内で入力してください。',
            'body.required' => '質問内容を入力してください。',
            'body.max' => '質問内容は5000文字以内で入力してください。',
        ];
    }
}
