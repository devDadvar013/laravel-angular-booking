<?php

namespace App\Models;

use App\Enums\BookingStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Booking extends Model
{
    use HasUuids;

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
}
