<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Mail\BookingConfirmationMail;
use App\Models\Booking;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class BookingTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_a_booking_when_there_is_no_time_conflict(): void
    {
        Mail::fake();

        $response = $this->postJson('/bookings', [
            'resourceId' => 'room-1',
            'customerName' => 'Ali Rezaei',
            'customerEmail' => 'ali@example.com',
            'startTime' => '2026-08-01T10:00:00Z',
            'endTime' => '2026-08-01T11:00:00Z',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('status', BookingStatus::PENDING->value);

        $this->assertDatabaseCount('bookings', 1);
        Mail::assertQueued(BookingConfirmationMail::class);
    }

    public function test_rejects_overlapping_bookings_for_the_same_resource(): void
    {
        Mail::fake();

        Booking::create([
            'resource_id' => 'room-1',
            'customer_name' => 'Sara',
            'customer_email' => 'sara@example.com',
            'start_time' => '2026-08-01T10:30:00Z',
            'end_time' => '2026-08-01T11:30:00Z',
            'status' => BookingStatus::CONFIRMED,
            'expires_at' => null,
        ]);

        $response = $this->postJson('/bookings', [
            'resourceId' => 'room-1',
            'customerName' => 'Ali',
            'customerEmail' => 'ali@example.com',
            'startTime' => '2026-08-01T10:00:00Z',
            'endTime' => '2026-08-01T11:00:00Z',
        ]);

        $response->assertStatus(409);
        $this->assertDatabaseCount('bookings', 1);
    }

    public function test_rejects_end_time_before_start_time(): void
    {
        $response = $this->postJson('/bookings', [
            'resourceId' => 'room-1',
            'customerName' => 'Ali',
            'customerEmail' => 'ali@example.com',
            'startTime' => '2026-08-01T11:00:00Z',
            'endTime' => '2026-08-01T10:00:00Z',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('endTime');
    }

    public function test_confirms_a_pending_booking(): void
    {
        $booking = Booking::create([
            'resource_id' => 'room-1',
            'customer_name' => 'Ali',
            'customer_email' => 'ali@example.com',
            'start_time' => now()->addHour(),
            'end_time' => now()->addHours(2),
            'status' => BookingStatus::PENDING,
            'expires_at' => now()->addMinutes(30),
        ]);

        $response = $this->patchJson("/bookings/{$booking->id}/confirm");

        $response->assertOk();
        $response->assertJsonPath('status', BookingStatus::CONFIRMED->value);
        $this->assertNull($response->json('expires_at'));
    }

    public function test_cancels_a_booking(): void
    {
        $booking = Booking::create([
            'resource_id' => 'room-1',
            'customer_name' => 'Ali',
            'customer_email' => 'ali@example.com',
            'start_time' => now()->addHour(),
            'end_time' => now()->addHours(2),
            'status' => BookingStatus::PENDING,
            'expires_at' => now()->addMinutes(30),
        ]);

        $response = $this->patchJson("/bookings/{$booking->id}/cancel");

        $response->assertOk();
        $response->assertJsonPath('status', BookingStatus::CANCELLED->value);
    }

    public function test_expires_overdue_pending_bookings_via_console_command(): void
    {
        $expired = Booking::create([
            'resource_id' => 'room-1',
            'customer_name' => 'Ali',
            'customer_email' => 'ali@example.com',
            'start_time' => now()->addHour(),
            'end_time' => now()->addHours(2),
            'status' => BookingStatus::PENDING,
            'expires_at' => now()->subMinute(),
        ]);

        Artisan::call('bookings:expire');

        $this->assertSame(
            BookingStatus::EXPIRED,
            $expired->fresh()->status
        );
    }

    public function test_lists_bookings_with_pagination_and_filters(): void
    {
        Booking::factory()->count(3)->create(['resource_id' => 'room-1']);
        Booking::factory()->count(2)->create(['resource_id' => 'room-2']);

        $response = $this->getJson('/bookings?resourceId=room-1&page=1&limit=10');

        $response->assertOk();
        $response->assertJsonCount(3, 'data');
        $response->assertJsonPath('meta.totalItems', 3);
    }
    public function test_database_health_endpoint_reports_schema(): void
    {
        $response = $this->getJson('/db-test');

        $response->assertOk();
        $response->assertJsonPath('status', 'connected');
        $response->assertJsonPath('bookings_table', 'bookings');
    }

}
