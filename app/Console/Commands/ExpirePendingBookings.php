<?php

namespace App\Console\Commands;

use App\Enums\BookingStatus;
use App\Models\Booking;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ExpirePendingBookings extends Command
{
    protected $signature = 'app:expire-pending-bookings';
    protected $description = '將超過 48 小時未確認的 pending 預約標記為 expired';

    public function handle(): void
    {
        $count = Booking::where('status', BookingStatus::Pending->value)
            ->where('created_at', '<=', now()->subHours(48))
            ->update(['status' => BookingStatus::Expired->value]);

        Log::info("ExpirePendingBookings: {$count} expired");
        $this->info("ExpirePendingBookings: {$count} bookings expired.");
    }
}
