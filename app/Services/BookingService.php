<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Exceptions\BookingConflictException;
use App\Mail\BookingConfirmationMail;
use App\Models\Booking;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class BookingService
{
    private readonly int $expirationMinutes;

    public function __construct()
    {
        $this->expirationMinutes = max(1, (int) env('BOOKING_EXPIRATION_MINUTES', 30));
    }

    private function availabilityCacheKey(string $resourceId, string $dateKey): string
    {
        return "availability:{$resourceId}:{$dateKey}";
    }

    /**
     * ایجاد رزرو با جلوگیری از race condition.
     *
     * روی PostgreSQL برای هر resourceId یک advisory lock در سطح transaction می‌گیریم.
     * این کار باعث می‌شود دو درخواست همزمان برای یک منبع نتوانند هر دو مرحله‌ی
     * «بررسی تداخل + INSERT» را همزمان انجام دهند.
     */
    public function createBooking(array $data): Booking
    {
        $startTime = new \DateTimeImmutable($data['startTime']);
        $endTime = new \DateTimeImmutable($data['endTime']);

        if ($endTime <= $startTime) {
            throw new \InvalidArgumentException('زمان پایان باید بعد از زمان شروع باشد.');
        }

        try {
            $booking = DB::transaction(function () use ($data, $startTime, $endTime) {
                // PostgreSQL/Neon: قفل transaction-level و بدون نیاز به نگه داشتن connection قبلی.
                if (DB::getDriverName() === 'pgsql') {
                    // SET TRANSACTION باید اولین دستور پس از BEGIN باشد.
                    DB::statement('SET TRANSACTION ISOLATION LEVEL SERIALIZABLE');

                    DB::statement(
                        'SELECT pg_advisory_xact_lock(hashtextextended(?, 0))',
                        [$data['resourceId']]
                    );
                }

                $overlapping = Booking::query()
                    ->where('resource_id', $data['resourceId'])
                    ->whereIn('status', [
                        BookingStatus::PENDING->value,
                        BookingStatus::CONFIRMED->value,
                    ])
                    ->where('start_time', '<', $endTime)
                    ->where('end_time', '>', $startTime)
                    ->exists();

                if ($overlapping) {
                    throw new BookingConflictException($data['resourceId']);
                }

                return Booking::create([
                    'resource_id' => $data['resourceId'],
                    'customer_name' => $data['customerName'],
                    'customer_email' => $data['customerEmail'],
                    'start_time' => $startTime,
                    'end_time' => $endTime,
                    'status' => BookingStatus::PENDING->value,
                    'expires_at' => now()->addMinutes($this->expirationMinutes),
                ]);
            }, 5);
        } catch (QueryException $e) {
            // خطای 25P02 فقط می‌گوید transaction قبلاً fail شده؛ علت اصلی را باید در لاگ ببینیم.
            Log::error('Database error while creating booking', [
                'sql_state' => $e->errorInfo[0] ?? $e->getCode(),
                'driver_code' => $e->errorInfo[1] ?? null,
                'message' => $e->getMessage(),
                'resource_id' => $data['resourceId'] ?? null,
            ]);

            throw $e;
        }

        // کش بعد از commit پاک می‌شود؛ خراب شدن Redis نباید رزرو ثبت‌شده را شکست دهد.
        try {
            $this->invalidateAvailabilityCache($booking->resource_id, $booking->start_time);
        } catch (\Throwable $e) {
            Log::warning('Failed to invalidate availability cache', [
                'booking_id' => $booking->id,
                'message' => $e->getMessage(),
            ]);
        }

        try {
            Mail::to($booking->customer_email)->queue(new BookingConfirmationMail($booking));
        } catch (\Throwable $e) {
            Log::error('Failed to queue booking confirmation email', [
                'booking_id' => $booking->id,
                'message' => $e->getMessage(),
            ]);
        }

        return $booking;
    }

    public function getAvailability(string $resourceId, \DateTimeInterface $date): array
    {
        $dateKey = $date->format('Y-m-d');
        $cacheKey = $this->availabilityCacheKey($resourceId, $dateKey);

        try {
            $cached = Cache::get($cacheKey);
            if ($cached !== null) {
                return $cached;
            }
        } catch (\Throwable $e) {
            Log::warning('Availability cache read failed', ['message' => $e->getMessage()]);
        }

        $dayStart = new \DateTimeImmutable("{$dateKey}T00:00:00Z");
        $dayEnd = $dayStart->modify('+1 day');

        $bookings = Booking::query()
            ->where('resource_id', $resourceId)
            ->whereIn('status', [
                BookingStatus::PENDING->value,
                BookingStatus::CONFIRMED->value,
            ])
            ->where('start_time', '<', $dayEnd)
            ->where('end_time', '>', $dayStart)
            ->orderBy('start_time')
            ->get()
            ->toArray();

        try {
            Cache::put($cacheKey, $bookings, max(1, (int) env('REDIS_TTL', 60)));
        } catch (\Throwable $e) {
            Log::warning('Availability cache write failed', ['message' => $e->getMessage()]);
        }

        return $bookings;
    }

    private function invalidateAvailabilityCache(string $resourceId, \DateTimeInterface $startTime): void
    {
        Cache::forget($this->availabilityCacheKey($resourceId, $startTime->format('Y-m-d')));
    }

    public function findAll(array $query): array
    {
        $page = max(1, (int) ($query['page'] ?? 1));
        $limit = min(100, max(1, (int) ($query['limit'] ?? 10)));

        $qb = Booking::query()->orderByDesc('created_at');

        if (! empty($query['resourceId'])) {
            $qb->where('resource_id', $query['resourceId']);
        }
        if (! empty($query['status'])) {
            $qb->where('status', $query['status']);
        }

        $totalItems = (clone $qb)->count();
        $data = $qb->forPage($page, $limit)->get();

        return [
            'data' => $data,
            'meta' => [
                'page' => $page,
                'limit' => $limit,
                'totalItems' => $totalItems,
                'totalPages' => (int) ceil($totalItems / $limit),
            ],
        ];
    }

    public function getResources(): array
    {
        return Booking::query()
            ->select('resource_id')
            ->distinct()
            ->orderBy('resource_id')
            ->pluck('resource_id')
            ->all();
    }

    public function findOne(string $id): Booking
    {
        $booking = Booking::find($id);

        if (! $booking) {
            throw new NotFoundHttpException("رزروی با شناسه {$id} یافت نشد");
        }

        return $booking;
    }

    public function confirmBooking(string $id): Booking
    {
        return DB::transaction(function () use ($id) {
            $booking = Booking::query()->lockForUpdate()->find($id);

            if (! $booking) {
                throw new NotFoundHttpException("رزروی با شناسه {$id} یافت نشد");
            }

            if ($booking->status !== BookingStatus::PENDING) {
                throw new BookingConflictException($booking->resource_id);
            }

            if ($booking->expires_at && $booking->expires_at->isPast()) {
                $booking->status = BookingStatus::EXPIRED;
                $booking->save();
                throw new NotFoundHttpException('مهلت این رزرو به پایان رسیده است. لطفاً دوباره رزرو کنید.');
            }

            $booking->status = BookingStatus::CONFIRMED;
            $booking->expires_at = null;
            $booking->save();

            return $booking->fresh();
        });
    }

    public function cancelBooking(string $id): Booking
    {
        $booking = DB::transaction(function () use ($id) {
            $booking = Booking::query()->lockForUpdate()->find($id);

            if (! $booking) {
                throw new NotFoundHttpException("رزروی با شناسه {$id} یافت نشد");
            }

            $booking->status = BookingStatus::CANCELLED;
            $booking->save();

            return $booking->fresh();
        });

        try {
            $this->invalidateAvailabilityCache($booking->resource_id, $booking->start_time);
        } catch (\Throwable $e) {
            Log::warning('Failed to invalidate availability cache after cancellation', [
                'booking_id' => $booking->id,
                'message' => $e->getMessage(),
            ]);
        }

        return $booking;
    }

    public function expireOverdueBookings(): int
    {
        return Booking::query()
            ->where('status', BookingStatus::PENDING->value)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->update(['status' => BookingStatus::EXPIRED->value]);
    }
}
