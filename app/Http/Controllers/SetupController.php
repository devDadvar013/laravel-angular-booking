<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Artisan;

/**
 * کنترلر راه‌اندازی از طریق URL — بدون نیاز به توکن.
 *
 * با زدن آدرس زیر، migration و seeder اجرا می‌شود:
 *   GET  /setup                (همه‌ی migration های pending اجرا + seeder)
 *   GET  /setup?fresh=1        (همه‌ی جدول‌ها drop و از نو ساخته می‌شود)
 *   POST /setup                (همان رفتار)
 *
 * خاموش کردن کامل مسیر:
 *   در .env بگذارید: SETUP_ENABLED=false
 *
 * محافظ production:
 *   در محیط production، ?fresh=1 فقط در صورتی کار می‌کند که
 *   ALLOW_DESTRUCTIVE_SETUP=true باشد. (migration معمولی همیشه مجاز است.)
 */
class SetupController extends Controller
{
    public function run(): JsonResponse
    {
        // ۱. بررسی فعال بودن قابلیت
        if (! env('SETUP_ENABLED', true)) {
            return response()->json([
                'ok' => false,
                'message' => 'مسیر setup با SETUP_ENABLED=false غیرفعال شده است.',
            ], 403);
        }

        // ۲. تعیین نوع اجرا
        $wantFresh = request()->boolean('fresh');
        if ($wantFresh && app()->environment('production') && ! env('ALLOW_DESTRUCTIVE_SETUP')) {
            return response()->json([
                'ok' => false,
                'message' => 'در production اجازه‌ی fresh وجود ندارد. ALLOW_DESTRUCTIVE_SETUP=true بگذار.',
            ], 403);
        }

        $migrateArgs = [
            '--seed' => true,
            '--force' => true,
        ];
        if ($wantFresh) {
            $migrateArgs['--fresh'] = true;
        }

        $startedAt = microtime(true);

        // ۳. اجرا
        try {
            $exitCode = Artisan::call('migrate', $migrateArgs);
        } catch (\Throwable $e) {
            $message = $e->getMessage();
            $hint = null;

            // اگر خطا «جدول تکراری» بود و fresh نخواسته، پیشنهاد بده
            if (! $wantFresh && (
                str_contains($message, 'already exists')
                || str_contains($message, 'Duplicate table')
                || str_contains($message, 'Base table or view already exists')
            )) {
                $hint = 'به نظر می‌رسد جدول‌ها از قبل وجود دارند. برای ساختن از نو، ?fresh=1 را به URL اضافه کن.';
            }

            return response()->json([
                'ok' => false,
                'message' => 'خطا در اجرای migration/seed: '.$message,
                'hint' => $hint,
            ], 500);
        }

        $duration = round(microtime(true) - $startedAt, 2);
        $output = Artisan::output();

        return response()->json([
            'ok' => $exitCode === 0,
            'exit_code' => $exitCode,
            'mode' => $wantFresh ? 'migrate:fresh --seed' : 'migrate --seed',
            'duration_seconds' => $duration,
            'output' => $output,
        ], $exitCode === 0 ? 200 : 500);
    }
}
