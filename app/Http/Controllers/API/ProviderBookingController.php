<?php

namespace App\Http\Controllers\API;

use App\Enums\BookingStatus;
use App\Enums\ScheduleStatus;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProviderBookingController extends Controller
{
    public function index(Request $request)
    {
        $provider = $request->user();
        $bookings = Booking::with(['member', 'schedule.divingOffer'])
            ->whereHas('schedule', fn($q) => $q->where('provider_id', $provider->id))
            ->orderByDesc('created_at')
            ->get()
            ->map(fn($b) => $this->formatBooking($b));

        return response()->json(['status' => true, 'data' => $bookings]);
    }

    public function confirm(Request $request, int $id)
    {
        $booking = Booking::with('schedule')->findOrFail($id);
        $this->authorizeProvider($request, $booking);

        if (!$booking->canTransitionTo(BookingStatus::Confirmed)) {
            return response()->json(['status' => false, 'message' => '當前狀態無法確認'], 422);
        }

        try {
            DB::transaction(function () use ($booking) {
                $schedule = $booking->schedule()->lockForUpdate()->first();
                $remaining = $schedule->max_participants - $schedule->current_participants;

                if ($booking->participants > $remaining) {
                    throw new \RuntimeException('名額不足，無法確認此預約');
                }

                $booking->update(['status' => BookingStatus::Confirmed]);
                $schedule->increment('current_participants', $booking->participants);
                $schedule->refresh();

                if ($schedule->current_participants >= $schedule->max_participants) {
                    $schedule->update(['status' => ScheduleStatus::Full]);
                }
            });
        } catch (\RuntimeException $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json(['status' => true, 'message' => '預約已確認', 'data' => $this->formatBooking($booking->fresh(['member', 'schedule.divingOffer']))]);
    }

    public function reject(Request $request, int $id)
    {
        $booking = Booking::with('schedule')->findOrFail($id);
        $this->authorizeProvider($request, $booking);

        if (!$booking->canTransitionTo(BookingStatus::Rejected)) {
            return response()->json(['status' => false, 'message' => '當前狀態無法拒絕'], 422);
        }

        $booking->update(['status' => BookingStatus::Rejected]);

        return response()->json(['status' => true, 'message' => '預約已拒絕']);
    }

    public function cancel(Request $request, int $id)
    {
        $booking = Booking::with('schedule')->findOrFail($id);
        $this->authorizeProvider($request, $booking);

        if (!$booking->canTransitionTo(BookingStatus::ProviderCancelled)) {
            return response()->json(['status' => false, 'message' => '當前狀態無法取消'], 422);
        }

        DB::transaction(function () use ($booking) {
            $schedule = $booking->schedule()->lockForUpdate()->first();
            $booking->update(['status' => BookingStatus::ProviderCancelled]);
            $schedule->decrement('current_participants', $booking->participants);
            $schedule->refresh();

            if ($schedule->current_participants < $schedule->max_participants
                && $schedule->status === ScheduleStatus::Full) {
                $schedule->update(['status' => ScheduleStatus::Open]);
            }
        });

        return response()->json(['status' => true, 'message' => '預約已取消']);
    }

    private function authorizeProvider(Request $request, Booking $booking): void
    {
        if ($booking->schedule->provider_id !== $request->user()->id) {
            abort(403, '無權操作此預約');
        }
    }

    private function formatBooking(Booking $b): array
    {
        return [
            'id'             => $b->id,
            'member_name'    => $b->member?->name,
            'member_email'   => $b->member?->email,
            'offer_title'    => $b->schedule?->divingOffer?->title,
            'scheduled_date' => $b->schedule?->scheduled_date?->toDateString(),
            'start_time'     => $b->schedule?->start_time,
            'participants'   => $b->participants,
            'total_price'    => $b->total_price,
            'status'         => $b->status->value,
            'notes'          => $b->notes,
            'created_at'     => $b->created_at?->toISOString(),
        ];
    }
}
