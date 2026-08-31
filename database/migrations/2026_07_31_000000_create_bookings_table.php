<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // اگر جدول از قبل وجود دارد، فقط ایندکس را بررسی کن
        if (Schema::hasTable('bookings')) {
            // اضافه کردن ایندکس اگر وجود ندارد
            if (!Schema::hasIndex('bookings', 'bookings_resource_id_start_time_end_time_index')) {
                Schema::table('bookings', function (Blueprint $table) {
                    $table->index(['resource_id', 'start_time', 'end_time']);
                });
            }
            return;
        }

        Schema::create('bookings', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // شناسه منبع قابل رزرو (اتاق، میز، دکتر، سالن و ...)
            $table->string('resource_id');

            $table->string('customer_name');
            $table->string('customer_email');

            $table->timestampTz('start_time');
            $table->timestampTz('end_time');

            // از string به جای enum استفاده می‌کنیم چون PostgreSQL native enum
            // با Laravel مشکل ایجاد می‌کند. validation در BookingService انجام می‌شود.
            $table->string('status', 20)
                ->default('pending');

            // مهلت پرداخت/تأیید؛ اگر تا این زمان تأیید نشود، توسط دستور
            // زمان‌بندی‌شده bookings:expire منقضی می‌شود
            $table->timestampTz('expires_at')->nullable();

            $table->timestampsTz();

            // ایندکس برای سرعت بخشیدن به کوئری تداخل زمانی، معادل
            // @Index(['resourceId', 'startTime', 'endTime']) در نسخه TypeORM اصلی
            $table->index(['resource_id', 'start_time', 'end_time']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
