<?php

use App\Http\Controllers\BookingController;
use App\Http\Controllers\SetupController;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

// ============================================================================
// مسیرهای راه‌اندازی دیتابیس (همه بدون توکن — در صورت نیاز SETUP_ENABLED=false)
// ============================================================================

// ۱. اجرای کامل migration (drop + از نو ساختن همه‌ی جدول‌ها) — بدون seed
Route::get('/run-migrations', function () {
    try {
        // قطع اتصال قبلی تا ترنزکیست خراب قبلی روی این درخواست تأثیر نگذارد
        DB::connection()->disconnect();
        Artisan::call('migrate:fresh', ['--force' => true]);
        return response()->json([
            'status' => 'success',
            'mode' => 'migrate:fresh',
            'output' => Artisan::output(),
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'status' => 'error',
            'message' => $e->getMessage(),
        ], 500);
    }
});

// ۲. اجرای همه‌ی migration + seeder (drop + از نو + seed)
Route::get('/run-migrations-with-seed', function () {
    try {
        DB::connection()->disconnect();
        Artisan::call('migrate:fresh', [
            '--seed'  => true,
            '--force' => true,
        ]);
        return response()->json([
            'status' => 'success',
            'mode' => 'migrate:fresh --seed',
            'output' => Artisan::output(),
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'status' => 'error',
            'message' => $e->getMessage(),
        ], 500);
    }
});

// ۳. wipe همه‌ی جدول‌ها و سپس فقط migration های pending را اجرا کن
Route::get('/force-reset', function () {
    try {
        DB::connection()->disconnect();
        Artisan::call('db:wipe', ['--force' => true]);
        Artisan::call('migrate', ['--force' => true]);
        return response()->json([
            'status' => 'success',
            'mode' => 'db:wipe + migrate',
            'output' => Artisan::output(),
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'status' => 'error',
            'message' => $e->getMessage(),
        ], 500);
    }
});

// ۴. مسیر /setup عمومی (همان کنترلر قبلی، migrate + seed)
Route::match(['get', 'post'], '/setup', [SetupController::class, 'run']);

// ============================================================================
// مسیرهای اصلی رزرو
// ============================================================================
Route::controller(BookingController::class)->prefix('bookings')->group(function () {
    Route::post('/', 'store');
    Route::get('/', 'index');

    // این دو مسیر باید قبل از '/{id}' تعریف شوند تا با آن اشتباه گرفته نشوند
    // (دقیقاً همان نکته‌ای که در کامنت booking.controller.ts نسخه NestJS اصلی بود)
    Route::get('/resources', 'resources');
    Route::get('/availability/{resourceId}', 'availability');

    Route::get('/{id}', 'show');
    Route::patch('/{id}/confirm', 'confirm');
    Route::patch('/{id}/cancel', 'cancel');
});
