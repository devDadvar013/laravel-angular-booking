<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('bookings', 'status')) {
            return;
        }

        // در نسخه‌های قدیمی پروژه ممکن است status یک PostgreSQL enum باشد.
        // اگر از قبل varchar است، هیچ ALTER غیرضروری اجرا نکن.
        $column = DB::selectOne(<<<'SQL'
            SELECT data_type
            FROM information_schema.columns
            WHERE table_schema = current_schema()
              AND table_name = 'bookings'
              AND column_name = 'status'
        SQL);

        if (! $column || $column->data_type === 'character varying') {
            return;
        }

        DB::statement(<<<'SQL'
            ALTER TABLE bookings
            ALTER COLUMN status TYPE varchar(20)
            USING status::text
        SQL);
    }

    public function down(): void
    {
        // عمداً enum را دوباره ایجاد نمی‌کنیم؛ varchar برای PostgreSQL و Laravel
        // پایدارتر است و validation در لایه‌ی application انجام می‌شود.
    }
};
