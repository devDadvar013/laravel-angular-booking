<?php

namespace App\Models;

use App\Enums\BookingStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Booking extends Model
{
    // HasUuids: شناسه‌ی UUID برای هر رزرو تولید می‌کند
    // HasFactory: متد factory() را فعال می‌کند تا seeder بتواند
    //             Booking::factory()->count(N)->create() بزند
    use HasFactory, HasUuids;

    protected $table = 'bookings';

    protected $fillable = [
        'resource_id',
        'customer_name',
        'customer_email',
        'start_time',
        'end_time',
        'status',
        'expires_at',
    ];

    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'expires_at' => 'datetime',
        'status' => BookingStatus::class,
    ];

    /**
     * ستون‌های دیتابیس به‌صورت snake_case هستند (resource_id, customer_name, ...)
     * ولی فرانت‌اند (Angular) انتظار camelCase دارد (resourceId, customerName, ...) —
     * دقیقاً همان چیزی که در CreateBookingRequest هم برای ورودی استفاده می‌شود.
     * این متد کلیدهای خروجی JSON/آرایه را برای هماهنگی کامل، camelCase می‌کند.
     */
    public function toArray(): array
    {
        $camelCased = [];

        foreach (parent::toArray() as $key => $value) {
            $camelCased[Str::camel($key)] = $value;
        }

        return $camelCased;
    }
}
