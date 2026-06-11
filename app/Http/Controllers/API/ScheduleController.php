<?php

namespace App\Http\Controllers\API;

use App\Enums\BookingStatus;
use App\Enums\ScheduleStatus;
use App\Http\Controllers\Controller;
use App\Models\CourseSchedule;
use App\Models\DivingOffer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ScheduleController extends Controller
{
    public function index(Request $request)
    {
        $provider = $request->user();
        $schedules = CourseSchedule::with('divingOffer')
            ->where('provider_id', $provider->id)
            ->orderBy('scheduled_date')
            ->orderBy('start_time')
            ->get()
            ->map(fn($s) => $this->formatSchedule($s));

        return response()->json(['status' => true, 'data' => $schedules]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'diving_offer_id'  => 'required|integer|exists:diving_offers,id',
            'scheduled_date'   => 'required|date|after_or_equal:today',
            'start_time'       => 'required|date_format:H:i',
            'max_participants' => 'required|integer|min:1',
        ]);

        $offer = DivingOffer::findOrFail($data['diving_offer_id']);
        if ($offer->provider_id !== $request->user()->id) {
            return response()->json(['status' => false, 'message' => '無權操作此課程'], 403);
        }

        $schedule = CourseSchedule::create([
            'diving_offer_id'     => $data['diving_offer_id'],
            'provider_id'         => $request->user()->id,
            'scheduled_date'      => $data['scheduled_date'],
            'start_time'          => $data['start_time'],
            'max_participants'    => $data['max_participants'],
            'current_participants'=> 0,
            'status'              => ScheduleStatus::Open,
        ]);

        return response()->json(['status' => true, 'data' => $this->formatSchedule($schedule)], 201);
    }

    public function update(Request $request, int $id)
    {
        $schedule = CourseSchedule::findOrFail($id);
        if ($schedule->provider_id !== $request->user()->id) {
            return response()->json(['status' => false, 'message' => '無權操作此時段'], 403);
        }

        $data = $request->validate([
            'start_time'       => 'sometimes|date_format:H:i',
            'max_participants' => 'sometimes|integer|min:1',
        ]);

        if (isset($data['max_participants']) && $data['max_participants'] < $schedule->current_participants) {
            return response()->json([
                'status'  => false,
                'message' => '人數上限不可低於目前已確認人數（' . $schedule->current_participants . '）',
            ], 422);
        }

        $schedule->update($data);

        return response()->json(['status' => true, 'data' => $this->formatSchedule($schedule->fresh())]);
    }

    public function destroy(Request $request, int $id)
    {
        $schedule = CourseSchedule::findOrFail($id);
        if ($schedule->provider_id !== $request->user()->id) {
            return response()->json(['status' => false, 'message' => '無權操作此時段'], 403);
        }

        DB::transaction(function () use ($schedule) {
            $schedule->update(['status' => ScheduleStatus::Cancelled]);
            $schedule->bookings()
                ->whereIn('status', [BookingStatus::Pending->value, BookingStatus::Confirmed->value])
                ->update(['status' => BookingStatus::ProviderCancelled->value]);
        });

        return response()->json(['status' => true, 'message' => '時段已取消']);
    }

    public function publicList(int $offerId)
    {
        $offer = DivingOffer::visibleToPublic()->findOrFail($offerId);
        $schedules = CourseSchedule::where('diving_offer_id', $offer->id)
            ->where('status', ScheduleStatus::Open->value)
            ->whereDate('scheduled_date', '>=', now()->toDateString())
            ->orderBy('scheduled_date')
            ->orderBy('start_time')
            ->get()
            ->map(fn($s) => [
                'id'               => $s->id,
                'scheduled_date'   => $s->scheduled_date->toDateString(),
                'start_time'       => $s->start_time,
                'max_participants' => $s->max_participants,
                'remaining_spots'  => $s->remainingSpots(),
                'status'           => $s->status->value,
            ]);

        return response()->json(['status' => true, 'data' => $schedules]);
    }

    private function formatSchedule(CourseSchedule $s): array
    {
        return [
            'id'                   => $s->id,
            'diving_offer_id'      => $s->diving_offer_id,
            'offer_title'          => $s->divingOffer?->title,
            'scheduled_date'       => $s->scheduled_date?->toDateString(),
            'start_time'           => $s->start_time,
            'max_participants'     => $s->max_participants,
            'current_participants' => $s->current_participants,
            'remaining_spots'      => $s->remainingSpots(),
            'status'               => $s->status->value,
        ];
    }
}
