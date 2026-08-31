<?php

use App\Http\Controllers\BookingController;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

// -----------------------------------------------------------------------------
// Health / diagnostics
// -----------------------------------------------------------------------------
Route::get('/db-test', function () {
    try {
        DB::connection()->getPdo();

        $driver = DB::getDriverName();
        $bookingsTable = null;
        $statusType = null;

        if ($driver === 'pgsql') {
            $schema = DB::selectOne(<<<'SQL'
                SELECT
                    to_regclass('public.bookings') AS bookings_table,
                    (SELECT data_type
                     FROM information_schema.columns
                     WHERE table_schema = 'public'
                       AND table_name = 'bookings'
                       AND column_name = 'status') AS status_type
            SQL);
            $bookingsTable = $schema->bookings_table ?? null;
            $statusType = $schema->status_type ?? null;
        } elseif ($driver === 'sqlite') {
            $bookingsTable = DB::getSchemaBuilder()->hasTable('bookings') ? 'bookings' : null;
        }

        return response()->json([
            'status' => 'connected',
            'driver' => $driver,
            'bookings_table' => $bookingsTable,
            'status_type' => $statusType,
        ]);
    } catch (\Throwable $e) {
        return response()->json([
            'status' => 'error',
            'message' => app()->hasDebugModeEnabled()
                ? $e->getMessage()
                : 'Database connection failed.',
        ], 500);
    }
});

// -----------------------------------------------------------------------------
// Optional setup endpoint. In production it requires SETUP_TOKEN.
// Use ?fresh=1 only when ALLOW_DESTRUCTIVE_SETUP=true.
// -----------------------------------------------------------------------------
Route::match(['get', 'post'], '/setup', function () {
    if (! env('SETUP_ENABLED', false)) {
        return response()->json(['ok' => false, 'message' => 'Setup is disabled.'], 403);
    }

    $expectedToken = (string) env('SETUP_TOKEN', '');
    $providedToken = (string) request()->header('X-Setup-Token', request()->query('token', ''));

    if ($expectedToken === '' || ! hash_equals($expectedToken, $providedToken)) {
        return response()->json(['ok' => false, 'message' => 'Unauthorized.'], 401);
    }

    $fresh = request()->boolean('fresh');
    if ($fresh && ! env('ALLOW_DESTRUCTIVE_SETUP', false)) {
        return response()->json([
            'ok' => false,
            'message' => 'Destructive setup is disabled. Set ALLOW_DESTRUCTIVE_SETUP=true temporarily.',
        ], 403);
    }

    try {
        $args = ['--force' => true, '--seed' => true];
        if ($fresh) {
            $args['--drop-views'] = true;
            Artisan::call('migrate:fresh', $args);
        } else {
            Artisan::call('migrate', $args);
        }

        return response()->json([
            'ok' => true,
            'mode' => $fresh ? 'migrate:fresh --seed' : 'migrate --seed',
            'output' => Artisan::output(),
        ]);
    } catch (\Throwable $e) {
        return response()->json([
            'ok' => false,
            'message' => app()->hasDebugModeEnabled() ? $e->getMessage() : 'Setup failed.',
        ], 500);
    }
});

// -----------------------------------------------------------------------------
// Booking API
// -----------------------------------------------------------------------------
Route::controller(BookingController::class)->prefix('bookings')->group(function () {
    Route::post('/', 'store');
    Route::get('/', 'index');
    Route::get('/resources', 'resources');
    Route::get('/availability/{resourceId}', 'availability');
    Route::patch('/{id}/confirm', 'confirm');
    Route::patch('/{id}/cancel', 'cancel');
    Route::get('/{id}', 'show');
});
