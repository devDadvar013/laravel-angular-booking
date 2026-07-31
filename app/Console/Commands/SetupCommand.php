<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * دستور یکپارچه برای راه‌اندازی اولیه‌ی پروژه:
 *   - کل دیتابیس را drop می‌کند (ایمن، فقط local/staging)
 *   - همه‌ی migration ها را از نو اجرا می‌کند
 *   - seeder ها را اجرا می‌کند (نمونه داده تولید می‌شود)
 *
 * استفاده:
 *   php artisan app:setup
 *   php artisan app:setup --keep   # بدون drop، فقط migrate --seed
 *   php artisan app:setup --force  # بدون تأیید
 */
class SetupCommand extends Command
{
    protected $signature = 'app:setup
                            {--keep : بدون drop کردن جدول‌ها، فقط migrate + seed}
                            {--force : بدون پرسیدن تأیید، اجرا می‌کند}';

    protected $description = 'اجرای کامل migration و seeder برای راه‌اندازی اولیه‌ی پروژه';

    public function handle(): int
    {
        $env = app()->environment();
        $dropTables = ! $this->option('keep');

        // ایمنی: در production بدون --force اجازه نمی‌دهیم.
        if ($env === 'production' && ! $this->option('force')) {
            $this->error('⛔ محیط production شناسایی شد. برای اجرای این دستور --force لازم است.');

            return self::FAILURE;
        }

        if ($dropTables && ! $this->option('force')) {
            $this->warn('⚠️  این دستور همه‌ی جدول‌های دیتابیس را حذف و از نو می‌سازد.');
            if (! $this->confirm('ادامه بدهم؟', false)) {
                $this->info('لغو شد.');

                return self::SUCCESS;
            }
        }

        // ۱. اجرای migration
        $this->info('🚀 در حال اجرای migration ها...');
        $migrateArgs = ['--seed' => true];
        if ($dropTables) {
            $migrateArgs['--fresh'] = true;
        }
        if ($this->option('force')) {
            $migrateArgs['--force'] = true;
        }

        $exit = $this->call('migrate', $migrateArgs);

        if ($exit !== self::SUCCESS) {
            $this->error('❌ اجرای migration ناموفق بود.');

            return $exit;
        }

        $this->info('✅ migration و seeder با موفقیت اجرا شدند.');

        return self::SUCCESS;
    }
}
