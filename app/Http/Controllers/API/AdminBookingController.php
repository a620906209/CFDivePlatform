<?php

namespace App\Http\Controllers\API;

use App\Enums\BookingStatus;
use App\Http\Controllers\Controller;
use App\Models\Booking;

class AdminBookingController extends Controller
{
    public function index()
    {
        $bookings = Booking::with(['member', 'schedule.divingOffer'])
            ->orderByDesc('created_at')
            ->get()
            ->map(fn($b) => [
                'id'             => $b->id,
                'member_name'    => $b->member?->name,
                'member_email'   => $b->member?->email,
                'offer_title'    => $b->schedule?->divingOffer?->title,
                'scheduled_date' => $b->schedule?->scheduled_date?->toDateString(),
                'start_time'     => $b->schedule?->start_time,
                'participants'   => $b->participants,
                'total_price'    => $b->total_price,
                'status'         => $b->status->value,
                'created_at'     => $b->created_at?->toISOString(),
            ]);

        return response()->json(['status' => true, 'data' => $bookings]);
    }

    public function complete(int $id)
    {
        $booking = Booking::findOrFail($id);

        if (!$booking->canTransitionTo(BookingStatus::Completed)) {
            return response()->json(['status' => false, 'message' => '只有已確認的預約才能標記完成'], 422);
        }

        $booking->update(['status' => BookingStatus::Completed]);

        return response()->json(['status' => true, 'message' => '預約已標記為完成']);
    }
}
