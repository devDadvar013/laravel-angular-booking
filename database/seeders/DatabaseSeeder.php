<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * نقطه‌ی ورود اصلی سیدرها. هر سیدر جدیدی که اضافه می‌کنی
     * اینجا صدا بزن تا با `php artisan db:seed` یا `migrate --seed`
     * اجرا شود.
     */
    public function run(): void
    {
        $this->call([
            BookingSeeder::class,
        ]);
    }
}
