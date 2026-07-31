<?php

namespace App\Http\Requests;

use App\Enums\BookingStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListBookingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'page' => ['sometimes', 'integer', 'min:1'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'resourceId' => ['sometimes', 'string'],
            'status' => ['sometimes', Rule::enum(BookingStatus::class)],
        ];
    }

    public function messages(): array
    {
        return [
            'page.integer' => 'page باید عدد صحیح باشد',
            'page.min' => 'page باید حداقل ۱ باشد',
            'limit.integer' => 'limit باید عدد صحیح باشد',
            'limit.min' => 'limit باید حداقل ۱ باشد',
            'limit.max' => 'limit نمی‌تواند بیشتر از ۱۰۰ باشد',
            'status.enum' => 'status نامعتبر است',
        ];
    }

    /**
     * مقادیر پیش‌فرض page=1 و limit=10، دقیقاً مثل مقدار پیش‌فرض در ListBookingsDto
     */
    public function validated($key = null, $default = null)
    {
        $data = parent::validated($key, $default);

        return array_merge([
            'page' => 1,
            'limit' => 10,
        ], $data);
    }
}
