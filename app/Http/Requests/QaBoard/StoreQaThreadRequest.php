<?php

declare(strict_types=1);

namespace App\Http\Requests\QaBoard;

use App\Enums\CertificationStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreQaThreadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
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
}
