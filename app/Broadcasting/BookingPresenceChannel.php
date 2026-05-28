<?php

namespace App\Broadcasting;

use App\Models\Booking;
use App\Models\User;

class BookingPresenceChannel
{
    public function join(User $user, Booking $booking): array|false
    {
        if ($booking->status->value !== 'confirmed') {
            return false;
        }

        $booking->loadMissing('schedule');

        $isMember = $user->role === 'member' && $booking->member_id === $user->id;
        $isProvider = $user->role === 'provider' && $booking->schedule->provider_id === $user->id;

        if (!$isMember && !$isProvider) {
            return false;
        }

        return [
            'user_id'   => $user->id,
            'user_type' => $user->role,
            'name'      => $user->name,
        ];
    }
}
