<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Exceptions\BookingConflictException;
use App\Mail\BookingConfirmationMail;
use App\Models\Booking;
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
        $this->expirationMinutes = (int) env('BOOKING_EXPIRATION_MINUTES', 30);
    }

    private function availabilityCacheKey(string $resourceId, string $dateKey): string
    {
        return "availability:{$resourceId}:{$dateKey}";
    }

    /**
     * ایجاد یک رزرو جدید با تضمین عدم تداخل زمانی.
     *
     * معادل نسخه NestJS که از تراکنش SERIALIZABLE + pessimistic_write lock استفاده می‌کرد:
     * اینجا سطح ایزوله‌سازی تراکنش PostgreSQL روی SERIALIZABLE تنظیم شده و ردیف‌های رزروهای
     * فعال همان منبع با lockForUpdate() (معادل SELECT ... FOR UPDATE) قفل می‌شوند تا در
     * صورت درخواست‌های همزمان روی همان بازه زمانی، race condition رخ ندهد.
     */
    public function createBooking(array $data): Booking
    {
        $startTime = new \DateTime($data['startTime']);
        $endTime = new \DateTime($data['endTime']);

        DB::statement('SET TRANSACTION ISOLATION LEVEL SERIALIZABLE');

        $booking = DB::transaction(function () use ($data, $startTime, $endTime) {
            $overlappingCount = Booking::query()
                ->where('resource_id', $data['resourceId'])
                ->whereIn('status', [BookingStatus::PENDING, BookingStatus::CONFIRMED])
                // شرط استاندارد هم‌پوشانی دو بازه زمانی:
                // existing.start < new.end AND existing.end > new.start
                ->where('start_time', '<', $endTime)
                ->where('end_time', '>', $startTime)
                ->lockForUpdate()
                // نکته: PostgreSQL اجازه نمی‌دهد FOR UPDATE با توابع تجمیعی مثل
                // count() ترکیب شود (خطای "FOR UPDATE is not allowed with
                // aggregate functions"). به همین دلیل ردیف‌ها را با get() واکشی
                // (و در همان لحظه قفل) می‌کنیم و تعداد آن‌ها را در PHP می‌شماریم.
                ->get()
                ->count();

            if ($overlappingCount > 0) {
                throw new BookingConflictException($data['resourceId']);
            }

            $expiresAt = now()->addMinutes($this->expirationMinutes);

            // رزرو ابتدا با وضعیت PENDING ثبت می‌شود و یک مهلت (expiresAt) دارد.
            // اگر تا آن زمان تأیید نهایی (confirmBooking) نشود، دستور زمان‌بندی‌شده
            // bookings:expire آن را EXPIRED می‌کند و بازه زمانی دوباره آزاد می‌شود.
            return Booking::create([
                'resource_id' => $data['resourceId'],
                'customer_name' => $data['customerName'],
                'customer_email' => $data['customerEmail'],
                'start_time' => $startTime,
                'end_time' => $endTime,
                'status' => BookingStatus::PENDING,
                'expires_at' => $expiresAt,
            ]);
        }, 3); // ۳ بار تلاش مجدد در صورت بروز deadlock

        // پاک‌سازی کش دسترسی‌پذیری این منبع چون وضعیت آن تغییر کرده
        $this->invalidateAvailabilityCache($booking->resource_id, $booking->start_time);

        // ارسال ایمیل تأیید به‌صورت غیربلاک‌کننده (queue)، خطای آن مانع پاسخ به کاربر نمی‌شود.
        // این دقیقاً معادل الگوی .catch() بدون await در نسخه NestJS اصلی است.
        try {
            Mail::to($booking->customer_email)->queue(new BookingConfirmationMail($booking));
        } catch (\Throwable $e) {
            Log::error("خطا در ارسال ایمیل تأیید: {$e->getMessage()}");
        }

        return $booking;
    }

    /**
     * دریافت لیست رزروهای فعال یک منبع در یک روز مشخص، با استفاده از کش Redis
     * تا فشار روی پایگاه داده در بازدیدهای مکرر (مثلاً چک کردن تقویم) کاهش یابد.
     */
    public function getAvailability(string $resourceId, \DateTimeInterface $date): array
    {
        $dateKey = $date->format('Y-m-d');
        $cacheKey = $this->availabilityCacheKey($resourceId, $dateKey);

        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $dayStart = new \DateTime("{$dateKey}T00:00:00");
        $dayEnd = new \DateTime("{$dateKey}T23:59:59.999999");

        $bookings = Booking::query()
            ->where('resource_id', $resourceId)
            ->whereIn('status', [BookingStatus::PENDING, BookingStatus::CONFIRMED])
            ->where('start_time', '<', $dayEnd)
            ->where('end_time', '>', $dayStart)
            ->orderBy('start_time')
            ->get()
            ->toArray();

        $ttlSeconds = (int) env('REDIS_TTL', 60);
        Cache::put($cacheKey, $bookings, $ttlSeconds);

        return $bookings;
    }

    private function invalidateAvailabilityCache(string $resourceId, \DateTimeInterface $startTime): void
    {
        $dateKey = $startTime->format('Y-m-d');
        Cache::forget($this->availabilityCacheKey($resourceId, $dateKey));
    }

    /**
     * لیست تمام رزروها با صفحه‌بندی (pagination) و فیلتر اختیاری بر اساس
     * resourceId و/یا status. جدیدترین رزروها ابتدا نمایش داده می‌شوند.
     */
    public function findAll(array $query): array
    {
        $page = (int) ($query['page'] ?? 1);
        $limit = (int) ($query['limit'] ?? 10);

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
                'totalPages' => $limit > 0 ? (int) ceil($totalItems / $limit) : 0,
            ],
        ];
    }

    /**
     * لیست یکتای شناسه‌ی تمام منابع (resourceId / اتاق‌ها) که تاکنون
     * حداقل یک رزرو برایشان ثبت شده است.
     */
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

    /**
     * تأیید نهایی یک رزرو PENDING (مثلاً پس از تکمیل پرداخت).
     * تنها رزروهایی که هنوز منقضی نشده‌اند قابل تأیید هستند.
     */
    public function confirmBooking(string $id): Booking
    {
        $booking = $this->findOne($id);

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

        return $booking;
    }

    public function cancelBooking(string $id): Booking
    {
        $booking = $this->findOne($id);
        $booking->status = BookingStatus::CANCELLED;
        $booking->save();

        $this->invalidateAvailabilityCache($booking->resource_id, $booking->start_time);

        return $booking;
    }

    /**
     * منقضی کردن رزروهایی که مهلت آن‌ها گذشته و هنوز در وضعیت PENDING مانده‌اند.
     * این متد توسط دستور زمان‌بندی‌شده bookings:expire فراخوانی می‌شود (هر ۵ دقیقه).
     */
    public function expireOverdueBookings(): int
    {
        return Booking::query()
            ->where('status', BookingStatus::PENDING)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->update(['status' => BookingStatus::EXPIRED]);
    }
}
