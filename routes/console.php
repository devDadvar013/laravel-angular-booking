<?php

use App\Console\Commands\ExpireBookings;
use Illuminate\Support\Facades\Schedule;

// معادل @Cron(CronExpression.EVERY_5_MINUTES) در tasks/cleanup.service.ts نسخه NestJS اصلی.
// برای فعال شدن باید یک کرون‌جاب سیستمی روی «php artisan schedule:run» هر دقیقه
// اجرا شود (یا در توسعه: php artisan schedule:work).
Schedule::command(ExpireBookings::class)->everyFiveMinutes();
