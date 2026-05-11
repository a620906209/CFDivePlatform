<?php

namespace App\Models;

use App\Enums\ScheduleStatus;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

class CourseSchedule extends Model
{
    protected $fillable = [
        'diving_offer_id',
        'provider_id',
        'scheduled_date',
        'start_time',
        'max_participants',
        'current_participants',
        'status',
    ];

    protected $casts = [
        'scheduled_date'       => 'date',
        'max_participants'     => 'integer',
        'current_participants' => 'integer',
        'status'               => ScheduleStatus::class,
    ];

    public function divingOffer()
    {
        return $this->belongsTo(DivingOffer::class);
    }

    public function provider()
    {
        return $this->belongsTo(User::class, 'provider_id');
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class, 'schedule_id');
    }

    public function remainingSpots(): int
    {
        return max(0, $this->max_participants - $this->current_participants);
    }

    protected function startTime(): Attribute
    {
        return Attribute::make(
            get: fn($value) => $value ? substr($value, 0, 5) : $value,
        );
    }
}
