<?php

namespace Database\Factories;

use App\Enums\BookingStatus;
use App\Models\Booking;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Booking>
 */
class BookingFactory extends Factory
{
    protected $model = Booking::class;

    public function definition(): array
    {
        $start = $this->faker->dateTimeBetween('+1 days', '+10 days');
        $end = (clone $start)->modify('+1 hour');

        return [
            'resource_id' => $this->faker->randomElement(['room-1', 'room-2', 'room-3']),
            'customer_name' => $this->faker->name(),
            'customer_email' => $this->faker->safeEmail(),
            'start_time' => $start,
            'end_time' => $end,
            'status' => BookingStatus::PENDING,
            'expires_at' => now()->addMinutes(30),
        ];
    }
}
