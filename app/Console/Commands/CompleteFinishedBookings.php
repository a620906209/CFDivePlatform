<?php

namespace App\Console\Commands;

use App\Enums\BookingStatus;
use App\Models\Booking;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CompleteFinishedBookings extends Command
{
    protected $signature = 'app:complete-finished-bookings';
    protected $description = '將課程日期已過的 confirmed 預約標記為 completed';

    public function handle(): void
    {
        $count = Booking::where('status', BookingStatus::Confirmed->value)
            ->whereHas('schedule', fn($q) => $q->whereDate('scheduled_date', '<', now()->toDateString()))
            ->update(['status' => BookingStatus::Completed->value]);

        Log::info("CompleteFinishedBookings: {$count} completed");
        $this->info("CompleteFinishedBookings: {$count} bookings completed.");
    }
}
