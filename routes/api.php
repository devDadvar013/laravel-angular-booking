<?php

use App\Http\Controllers\BookingController;
use Illuminate\Support\Facades\Route;

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
