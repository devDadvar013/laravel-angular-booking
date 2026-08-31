<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // تغییر نوع ستون status از enum به varchar
        // چون PostgreSQL native enum با Laravel مشکل ایجاد می‌کند
        if (Schema::hasColumn('bookings', 'status')) {
            DB::statement("ALTER TABLE bookings ALTER COLUMN status TYPE varchar(20) USING status::varchar");
        }
    }

    public function down(): void
    {
        // بازگشت به enum (در صورت نیاز)
        if (Schema::hasColumn('bookings', 'status')) {
            DB::statement("ALTER TABLE bookings ALTER COLUMN status TYPE varchar(20)");
        }
    }
};
