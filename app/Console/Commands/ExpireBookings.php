<?php

namespace App\Console\Commands;

use App\Services\BookingService;
use Illuminate\Console\Command;

class ExpireBookings extends Command
{
    protected $signature = 'bookings:expire';

    protected $description = 'منقضی کردن رزروهای PENDING که مهلت‌شان گذشته است (معادل CleanupService در نسخه NestJS)';

    public function handle(BookingService $bookingService): int
    {
        $this->info('شروع بررسی رزروهای منقضی‌شده...');

        try {
            $count = $bookingService->expireOverdueBookings();
            $this->info("بررسی پایان یافت. تعداد رزروهای منقضی‌شده: {$count}");
        } catch (\Throwable $e) {
            $this->error("خطا در پاک‌سازی رزروهای منقضی‌شده: {$e->getMessage()}");

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
