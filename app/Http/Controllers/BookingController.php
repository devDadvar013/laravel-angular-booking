<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateBookingRequest;
use App\Http\Requests\ListBookingsRequest;
use App\Services\BookingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BookingController extends Controller
{
    public function __construct(private readonly BookingService $bookingService)
    {
    }

    // POST /bookings
    public function store(CreateBookingRequest $request): JsonResponse
    {
        $booking = $this->bookingService->createBooking($request->validated());

        return response()->json($booking, 201);
    }

    // GET /bookings?page=1&limit=10&resourceId=room-1&status=confirmed
    public function index(ListBookingsRequest $request): JsonResponse
    {
        return response()->json($this->bookingService->findAll($request->validated()));
    }

    // GET /bookings/resources
    // توجه: باید قبل از مسیر ':id' ثبت شود تا با آن اشتباه گرفته نشود (در routes/api.php رعایت شده)
    public function resources(): JsonResponse
    {
        return response()->json($this->bookingService->getResources());
    }

    // GET /bookings/availability/{resourceId}?date=YYYY-MM-DD
    public function availability(string $resourceId, Request $request): JsonResponse
    {
        $date = $request->query('date')
            ? new \DateTime($request->query('date'))
            : new \DateTime();

        return response()->json($this->bookingService->getAvailability($resourceId, $date));
    }

    // GET /bookings/{id}
    public function show(string $id): JsonResponse
    {
        return response()->json($this->bookingService->findOne($id));
    }

    // PATCH /bookings/{id}/confirm
    public function confirm(string $id): JsonResponse
    {
        return response()->json($this->bookingService->confirmBooking($id));
    }

    // PATCH /bookings/{id}/cancel
    public function cancel(string $id): JsonResponse
    {
        return response()->json($this->bookingService->cancelBooking($id));
    }
}
