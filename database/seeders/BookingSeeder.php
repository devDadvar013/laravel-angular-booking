<?php

namespace Database\Seeders;

use App\Enums\BookingStatus;
use App\Models\Booking;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * سیدر نمونه برای جدول bookings.
 *
 * یک مجموعه‌ی متنوع از رزروها تولید می‌کند تا هر چهار وضعیت ممکن
 * (pending, confirmed, cancelled, expired) و چند منبع متفاوت
 * در دیتابیس وجود داشته باشد. این کار برای تست API و رابط کاربری
 * در همان ابتدای کار مفید است.
 */
class BookingSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();

        // --- رزروهای CONFIRMED (تأییدشده) برای ۵ روز آینده ---------------------
        $confirmed = [
            [
                'resource_id' => 'room-1',
                'customer_name' => 'علی رضایی',
                'customer_email' => 'ali.rezaei@example.com',
                'days_offset' => 1,
                'hour' => 10,
                'duration_hours' => 1,
            ],
            [
                'resource_id' => 'room-2',
                'customer_name' => 'مریم کریمی',
                'customer_email' => 'maryam.karimi@example.com',
                'days_offset' => 2,
                'hour' => 14,
                'duration_hours' => 2,
            ],
            [
                'resource_id' => 'room-3',
                'customer_name' => 'حسین مرادی',
                'customer_email' => 'hossein.moradi@example.com',
                'days_offset' => 3,
                'hour' => 9,
                'duration_hours' => 1,
            ],
            [
                'resource_id' => 'room-1',
                'customer_name' => 'زهرا صادقی',
                'customer_email' => 'zahra.sadeghi@example.com',
                'days_offset' => 4,
                'hour' => 16,
                'duration_hours' => 3,
            ],
        ];

        foreach ($confirmed as $row) {
            $start = $now->copy()->addDays($row['days_offset'])->setTime($row['hour'], 0);
            $end = (clone $start)->addHours($row['duration_hours']);

            Booking::create([
                'resource_id' => $row['resource_id'],
                'customer_name' => $row['customer_name'],
                'customer_email' => $row['customer_email'],
                'start_time' => $start,
                'end_time' => $end,
                'status' => BookingStatus::CONFIRMED,
                'expires_at' => null,
            ]);
        }

        // --- رزروهای PENDING (در انتظار تأیید) برای امروز/فردا -------------
        $pending = [
            [
                'resource_id' => 'room-2',
                'customer_name' => 'رضا قاسمی',
                'customer_email' => 'reza.ghasemi@example.com',
                'days_offset' => 0,
                'hour' => 18,
                'duration_hours' => 1,
            ],
            [
                'resource_id' => 'room-1',
                'customer_name' => 'نگار احمدی',
                'customer_email' => 'negar.ahmadi@example.com',
                'days_offset' => 1,
                'hour' => 12,
                'duration_hours' => 2,
            ],
        ];

        foreach ($pending as $row) {
            $start = $now->copy()->addDays($row['days_offset'])->setTime($row['hour'], 0);
            $end = (clone $start)->addHours($row['duration_hours']);

            Booking::create([
                'resource_id' => $row['resource_id'],
                'customer_name' => $row['customer_name'],
                'customer_email' => $row['customer_email'],
                'start_time' => $start,
                'end_time' => $end,
                'status' => BookingStatus::PENDING,
                // ۳۰ دقیقه فرصت برای تأیید؛ بعدش توسط bookings:expire منقضی می‌شود
                'expires_at' => $now->copy()->addMinutes(30),
            ]);
        }

        // --- رزروهای CANCELLED (لغوشده) -------------------------------------
        $cancelled = [
            [
                'resource_id' => 'room-3',
                'customer_name' => 'مهدی نیک‌نام',
                'customer_email' => 'mahdi.niknam@example.com',
                'days_offset' => 5,
                'hour' => 11,
                'duration_hours' => 1,
            ],
            [
                'resource_id' => 'room-2',
                'customer_name' => 'سارا موسوی',
                'customer_email' => 'sara.mousavi@example.com',
                'days_offset' => 6,
                'hour' => 15,
                'duration_hours' => 2,
            ],
        ];

        foreach ($cancelled as $row) {
            $start = $now->copy()->addDays($row['days_offset'])->setTime($row['hour'], 0);
            $end = (clone $start)->addHours($row['duration_hours']);

            Booking::create([
                'resource_id' => $row['resource_id'],
                'customer_name' => $row['customer_name'],
                'customer_email' => $row['customer_email'],
                'start_time' => $start,
                'end_time' => $end,
                'status' => BookingStatus::CANCELLED,
                'expires_at' => null,
            ]);
        }

        // --- رزروهای EXPIRED (منقضی‌شده) برای گذشته -------------------------
        $expired = [
            [
                'resource_id' => 'room-1',
                'customer_name' => 'امیر حسینی',
                'customer_email' => 'amir.hosseini@example.com',
                'days_offset' => -2,
                'hour' => 10,
                'duration_hours' => 1,
            ],
        ];

        foreach ($expired as $row) {
            $start = $now->copy()->addDays($row['days_offset'])->setTime($row['hour'], 0);
            $end = (clone $start)->addHours($row['duration_hours']);

            Booking::create([
                'resource_id' => $row['resource_id'],
                'customer_name' => $row['customer_name'],
                'customer_email' => $row['customer_email'],
                'start_time' => $start,
                'end_time' => $end,
                'status' => BookingStatus::EXPIRED,
                'expires_at' => $start->copy()->subMinutes(5), // قبل از شروع منقضی شده
            ]);
        }

        // در نهایت ۱۰ رزرو تصادفی هم با factory اضافه می‌کنیم
        // تا حجم داده‌ی واقعی‌تری داشته باشیم (از conflict چشم‌پوشی می‌شود
        // چون هدف seeder نمایش داده است، نه تست همزمانی).
        Booking::factory()->count(10)->create();
    }
}
