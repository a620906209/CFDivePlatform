<?php

namespace App\Console\Commands;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Notifications\BookingCompletedNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CompleteFinishedBookings extends Command
{
    protected $signature = 'app:complete-finished-bookings';
    protected $description = '將課程日期已過的 confirmed 預約標記為 completed';

    public function handle(): void
    {
        $bookings = Booking::with(['member', 'schedule.divingOffer'])
            ->where('status', BookingStatus::Confirmed->value)
            ->whereHas('schedule', fn($q) => $q->whereDate('scheduled_date', '<', now()->toDateString()))
            ->get();

        $count = 0;
        foreach ($bookings as $booking) {
            $booking->update(['status' => BookingStatus::Completed]);
            $count++;

            try {
                $booking->member->notify(new BookingCompletedNotification($booking));
            } catch (\Throwable $e) {
                Log::error("BookingCompletedNotification failed for booking #{$booking->id}: " . $e->getMessage());
            }
        }

        Log::info("CompleteFinishedBookings: {$count} completed");
        $this->info("CompleteFinishedBookings: {$count} bookings completed.");
    }
}
