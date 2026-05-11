<?php

namespace App\Models;

use App\Enums\BookingStatus;
use Illuminate\Database\Eloquent\Model;

class Booking extends Model
{
    protected $fillable = [
        'schedule_id',
        'member_id',
        'participants',
        'total_price',
        'status',
        'notes',
    ];

    protected $casts = [
        'participants' => 'integer',
        'total_price'  => 'integer',
        'status'       => BookingStatus::class,
    ];

    const VALID_TRANSITIONS = [
        'pending'            => ['confirmed', 'rejected', 'expired', 'member_cancelled'],
        'confirmed'          => ['completed', 'member_cancelled', 'provider_cancelled'],
        'completed'          => [],
        'rejected'           => [],
        'expired'            => [],
        'member_cancelled'   => [],
        'provider_cancelled' => [],
    ];

    public function canTransitionTo(BookingStatus $newStatus): bool
    {
        $current = $this->status->value;
        $allowed = self::VALID_TRANSITIONS[$current] ?? [];
        return in_array($newStatus->value, $allowed);
    }

    public function schedule()
    {
        return $this->belongsTo(CourseSchedule::class, 'schedule_id');
    }

    public function member()
    {
        return $this->belongsTo(User::class, 'member_id');
    }
}
