<?php

namespace App\Http\Controllers\API;

use App\Enums\BookingStatus;
use App\Enums\ScheduleStatus;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\CourseSchedule;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MemberBookingController extends Controller
{
    public function index(Request $request)
    {
        $bookings = Booking::with(['schedule.divingOffer'])
            ->where('member_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn($b) => $this->formatBooking($b));

        return response()->json(['status' => true, 'data' => $bookings]);
    }

    public function show(Request $request, int $id)
    {
        $booking = Booking::with(['schedule.divingOffer'])->findOrFail($id);
        if ($booking->member_id !== $request->user()->id) {
            return response()->json(['status' => false, 'message' => '無權查看此預約'], 403);
        }

        return response()->json(['status' => true, 'data' => $this->formatBooking($booking)]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'schedule_id'  => 'required|integer|exists:course_schedules,id',
            'participants' => 'required|integer|min:1',
            'notes'        => 'nullable|string|max:500',
        ]);

        $schedule = CourseSchedule::with('divingOffer')->findOrFail($data['schedule_id']);

        // Layer 1：快速失敗
        if ($schedule->status !== ScheduleStatus::Open) {
            return response()->json(['status' => false, 'message' => '此時段不開放預約'], 422);
        }
        if ($data['participants'] > $schedule->remainingSpots()) {
            return response()->json(['status' => false, 'message' => '人數超過剩餘名額'], 422);
        }

        $memberId = $request->user()->id;

        try {
            $booking = DB::transaction(function () use ($data, $schedule, $memberId) {
                // Layer 2：lockForUpdate 後二次驗證
                $schedule = CourseSchedule::lockForUpdate()->find($schedule->id);
                if ($data['participants'] > $schedule->remainingSpots()) {
                    throw new \RuntimeException('名額不足，請重新選擇');
                }

                // 重複預約檢查
                $duplicate = Booking::where('member_id', $memberId)
                    ->where('schedule_id', $schedule->id)
                    ->whereIn('status', [BookingStatus::Pending->value, BookingStatus::Confirmed->value])
                    ->exists();
                if ($duplicate) {
                    throw new \RuntimeException('您已預約此時段');
                }

                return Booking::create([
                    'schedule_id'  => $schedule->id,
                    'member_id'    => $memberId,
                    'participants' => $data['participants'],
                    'total_price'  => $schedule->divingOffer->price * $data['participants'],
                    'status'       => BookingStatus::Pending,
                    'notes'        => $data['notes'] ?? null,
                ]);
            });
        } catch (\RuntimeException $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'status'  => true,
            'message' => '預約已送出，等待教練確認',
            'data'    => $this->formatBooking($booking->fresh(['schedule.divingOffer'])),
        ], 201);
    }

    public function destroy(Request $request, int $id)
    {
        $booking = Booking::with('schedule')->findOrFail($id);
        if ($booking->member_id !== $request->user()->id) {
            return response()->json(['status' => false, 'message' => '無權操作此預約'], 403);
        }

        $canCancelFrom = [BookingStatus::Pending, BookingStatus::Confirmed];
        if (!in_array($booking->status, $canCancelFrom)) {
            return response()->json(['status' => false, 'message' => '此預約狀態無法取消'], 422);
        }

        // 24h 截止驗證
        $schedule = $booking->schedule;
        $courseStart = Carbon::parse($schedule->scheduled_date->toDateString() . ' ' . $schedule->start_time);
        if (now()->diffInHours($courseStart, false) < 24) {
            return response()->json(['status' => false, 'message' => '距課程開始不足 24 小時，無法取消，請聯繫教練'], 422);
        }

        DB::transaction(function () use ($booking, $schedule) {
            $wasConfirmed = $booking->status === BookingStatus::Confirmed;
            $booking->update(['status' => BookingStatus::MemberCancelled]);

            if ($wasConfirmed) {
                $schedule = $booking->schedule()->lockForUpdate()->first();
                $schedule->decrement('current_participants', $booking->participants);
                $schedule->refresh();

                if ($schedule->current_participants < $schedule->max_participants
                    && $schedule->status === ScheduleStatus::Full) {
                    $schedule->update(['status' => ScheduleStatus::Open]);
                }
            }
        });

        return response()->json(['status' => true, 'message' => '預約已取消']);
    }

    private function formatBooking(Booking $b): array
    {
        $offer = $b->schedule?->divingOffer;
        return [
            'id'             => $b->id,
            'offer_id'       => $offer?->id,
            'offer_title'    => $offer?->title,
            'offer_location' => $offer?->location,
            'offer_region'   => $offer?->region,
            'offer_price'    => $offer?->price,
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
