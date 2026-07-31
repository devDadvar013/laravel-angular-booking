<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'resourceId' => ['required', 'string'],
            'customerName' => ['required', 'string'],
            'customerEmail' => ['required', 'email'],
            'startTime' => ['required', 'date'],
            // معادل @Validate(IsEndAfterStartConstraint) در نسخه NestJS اصلی:
            // endTime باید دقیقاً بعد از startTime باشد
            'endTime' => ['required', 'date', 'after:startTime'],
        ];
    }

    public function messages(): array
    {
        return [
            'resourceId.required' => 'شناسه منبع (resourceId) الزامی است',
            'customerName.required' => 'نام مشتری الزامی است',
            'customerEmail.required' => 'ایمیل معتبر وارد کنید',
            'customerEmail.email' => 'ایمیل معتبر وارد کنید',
            'startTime.required' => 'startTime باید یک تاریخ معتبر ISO باشد',
            'startTime.date' => 'startTime باید یک تاریخ معتبر ISO باشد',
            'endTime.required' => 'endTime باید یک تاریخ معتبر ISO باشد',
            'endTime.date' => 'endTime باید یک تاریخ معتبر ISO باشد',
            'endTime.after' => 'زمان پایان (endTime) باید بعد از زمان شروع (startTime) باشد',
        ];
    }
}
