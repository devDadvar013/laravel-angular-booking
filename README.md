# سیستم رزرواسیون آنلاین — نسخه Laravel + PostgreSQL

این پروژه بازنویسی کامل نسخه‌ی اصلی **NestJS + PostgreSQL + TypeORM + Redis** با
**Laravel 12 + PostgreSQL + Redis** است. تمام منطق تجاری (جلوگیری از تداخل زمانی،
کش availability، ارسال ایمیل تأیید، منقضی‌سازی خودکار رزروهای معلق) دقیقاً حفظ شده است.

---

## ⚠️ نکته‌ی امنیتی مهم درباره‌ی فایل اصلی

فایل `.env` داخل زیپ اصلی که فرستادید، شامل **credentialهای واقعی و فعال** بود:

- یک connection string کامل PostgreSQL روی Neon، همراه با پسورد
- یک URL کامل Redis روی Upstash، همراه با پسورد

این‌ها در فایل‌های این پروژه‌ی جدید **استفاده نشده‌اند** و به‌جایشان تنظیمات محلی/نمونه
برای PostgreSQL گذاشته شده (که با اعتبارنامه‌های واقعی فرق دارد). اما پیشنهاد جدی می‌کنم:

1. هرچه سریع‌تر پسورد دیتابیس Neon و پسورد Upstash Redis را از پنل خودشان rotate/revoke کنید.
2. اگر این فایل در گیت هم commit شده، آن را از تاریخچه هم پاک کنید (`git filter-repo` یا مشابه).
3. از این به بعد فقط `.env.example` (بدون مقدار واقعی) را commit کنید و `.env` واقعی را در `.gitignore` نگه دارید (این پروژه از قبل این‌طور تنظیم شده).

---

## تفاوت‌های اصلی نسبت به نسخه‌ی NestJS

| مورد | نسخه‌ی NestJS اصلی | این نسخه (Laravel) |
|---|---|---|
| دیتابیس | PostgreSQL (TypeORM) | **PostgreSQL** (Eloquent) |
| ایزوله‌سازی تراکنش | `SERIALIZABLE` + `pessimistic_write` | `SET TRANSACTION ISOLATION LEVEL SERIALIZABLE` + `lockForUpdate()` |
| کش | `@nestjs/cache-manager` + Redis | Laravel `Cache` facade با درایور `redis` |
| ارسال ایمیل | nodemailer، fire-and-forget (`.catch()` بدون await) | `Mail::queue()` (صف Redis/DB)، غیربلاک‌کننده |
| Cron | `@nestjs/schedule` هر ۵ دقیقه | `routes/console.php` + `Schedule::command()->everyFiveMinutes()` |
| اعتبارسنجی | `class-validator` DTOها | `FormRequest`ها با همان قوانین و پیام‌های فارسی |
| خطای تداخل | `ConflictException` → HTTP 409 | `BookingConflictException` → رندر HTTP 409 در `bootstrap/app.php` |

نکته درباره‌ی تراکنش: چون دیتابیس دقیقاً همان PostgreSQL نسخه‌ی اصلی است، سطح ایزوله‌سازی
`SERIALIZABLE` هم رفتار یکسانی دارد. علاوه بر آن، `lockForUpdate()` (معادل
`SELECT ... FOR UPDATE`) روی رزروهای فعال همان منبع اعمال می‌شود تا در تراکنش‌های همزمان
هیچ درخواست دیگری هم‌زمان همان بازه زمانی را رزرو نکند — دقیقاً همان الگوی
`pessimistic_write` نسخه‌ی TypeORM اصلی.

---

## پیش‌نیازها

- PHP 8.2+
- Composer
- PostgreSQL 14+
- Redis
- (اختیاری) Docker و Docker Compose

## نصب و راه‌اندازی (بدون Docker)

```bash
composer install
cp .env.example .env      # یا از .env آماده‌ی این پروژه استفاده کنید
php artisan key:generate
```

سپس در `.env` مقادیر زیر را با اطلاعات دیتابیس PostgreSQL و Redis خودتان تنظیم کنید:

```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=booking_db
DB_USERNAME=postgres
DB_PASSWORD=your-password

CACHE_STORE=redis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
```

### اتصال به دیتابیس/Redis ابری (مثل Neon و Upstash)

اگر به‌جای دیتابیس محلی می‌خواهید به یک سرویس ابری وصل شوید — دقیقاً مثل
`configService.get('DATABASE_URL')` در نسخه‌ی NestJS اصلی — کافی است این دو متغیر
را در `.env` پر کنید؛ در این صورت مقادیر `DB_HOST`/`DB_PORT`/... و
`REDIS_HOST`/`REDIS_PORT`/... نادیده گرفته می‌شوند (پیاده‌سازی در `config/database.php`):

```env
DATABASE_URL=postgresql://user:pass@host/dbname?sslmode=require
REDIS_URL=rediss://default:password@host:6379
```

⚠️ اگر از یک دیتابیس/Redis اشتراکی یا production واقعی استفاده می‌کنید، مراقب باشید این
مقادیر را در گیت commit نکنید (`.env` از قبل در `.gitignore` این پروژه قرار دارد).

اجرای migration:

```bash
php artisan migrate
```

اجرای سرور توسعه:

```bash
php artisan serve
# API روی http://localhost:8000/bookings در دسترس است
```

اجرای صف (برای ارسال ایمیل تأیید بدون بلاک کردن پاسخ API؛ اگر `QUEUE_CONNECTION=sync`
باشد این مرحله لازم نیست، اما ایمیل به‌صورت synchronous ارسال می‌شود):

```bash
php artisan queue:work
```

اجرای scheduler (برای منقضی‌سازی خودکار رزروهای معلق هر ۵ دقیقه):

```bash
php artisan schedule:work
```

یا در production، یک کرون‌جاب سیستمی هر دقیقه:

```
* * * * * cd /path-to-project && php artisan schedule:run >> /dev/null 2>&1
```

## اجرا با Docker

```bash
docker compose up --build
```

این دستور ۴ سرویس را بالا می‌آورد: `app` (API روی پورت 8000)، `postgres`، `redis`،
`scheduler` (اجرای مداوم `schedule:work`)، و `queue-worker` (پردازش صف ایمیل).
بعد از بالا آمدن کانتینرها، یک‌بار migration را اجرا کنید:

```bash
docker compose exec app php artisan migrate
```

## اجرای تست‌ها

تست‌ها با SQLite درون‌حافظه‌ای اجرا می‌شوند (نیازی به PostgreSQL واقعی نیست):

```bash
php artisan test
# یا
./vendor/bin/phpunit
```

---

## مستندات API

| متد | مسیر | توضیح |
|---|---|---|
| `POST` | `/bookings` | ایجاد رزرو جدید (وضعیت اولیه: `pending`) |
| `GET` | `/bookings?page=&limit=&resourceId=&status=` | لیست رزروها با صفحه‌بندی و فیلتر |
| `GET` | `/bookings/resources` | لیست یکتای شناسه‌ی منابع رزرو شده |
| `GET` | `/bookings/availability/{resourceId}?date=YYYY-MM-DD` | لیست رزروهای فعال آن منبع در آن روز (کش‌شده در Redis) |
| `GET` | `/bookings/{id}` | جزئیات یک رزرو |
| `PATCH` | `/bookings/{id}/confirm` | تأیید نهایی رزرو `pending` |
| `PATCH` | `/bookings/{id}/cancel` | لغو رزرو |

### نمونه درخواست ایجاد رزرو

```bash
curl -X POST http://localhost:8000/bookings \
  -H "Content-Type: application/json" \
  -d '{
    "resourceId": "room-1",
    "customerName": "Ali Rezaei",
    "customerEmail": "ali@example.com",
    "startTime": "2026-08-01T10:00:00Z",
    "endTime": "2026-08-01T11:00:00Z"
  }'
```

در صورت تداخل زمانی با یک رزرو `pending` یا `confirmed` دیگر روی همان `resourceId`،
پاسخ `409 Conflict` با پیام فارسی برگردانده می‌شود.

## ساختار پروژه

```
app/
  Console/Commands/ExpireBookings.php   # معادل CleanupService
  Enums/BookingStatus.php               # معادل enum BookingStatus
  Exceptions/BookingConflictException.php
  Http/Controllers/BookingController.php
  Http/Requests/CreateBookingRequest.php
  Http/Requests/ListBookingsRequest.php
  Mail/BookingConfirmationMail.php      # معادل MailService
  Models/Booking.php
  Services/BookingService.php           # معادل BookingService (منطق اصلی)
database/migrations/..._create_bookings_table.php
routes/api.php                          # معادل booking.controller.ts
routes/console.php                      # زمان‌بندی bookings:expire
resources/views/emails/booking-confirmation.blade.php
tests/Feature/BookingTest.php
```
