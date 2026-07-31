<?php

namespace App\Enums;

enum BookingStatus: string
{
    case PENDING = 'pending';     // در انتظار تأیید پرداخت/تأیید ایمیل
    case CONFIRMED = 'confirmed'; // تأیید نهایی شده
    case CANCELLED = 'cancelled'; // لغو شده توسط کاربر یا سیستم
    case EXPIRED = 'expired';     // منقضی شده (توسط دستور زمان‌بندی‌شده پاک‌سازی می‌شود)
}
