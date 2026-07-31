<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // شناسه منبع قابل رزرو (اتاق، میز، دکتر، سالن و ...)
            $table->string('resource_id');

            $table->string('customer_name');
            $table->string('customer_email');

            $table->timestampTz('start_time');
            $table->timestampTz('end_time');

            $table->enum('status', ['pending', 'confirmed', 'cancelled', 'expired'])
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
