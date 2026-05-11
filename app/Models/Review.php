<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Review extends Model
{
    protected $fillable = [
        'diving_offer_id',
        'member_id',
        'rating',
        'comment',
        'helpful_count',
        'is_edited',
    ];

    protected $casts = [
        'rating'        => 'integer',
        'helpful_count' => 'integer',
        'is_edited'     => 'boolean',
    ];

    public function divingOffer()
    {
        return $this->belongsTo(DivingOffer::class);
    }

    public function member()
    {
        return $this->belongsTo(User::class, 'member_id');
    }

    public function edit()
    {
        return $this->hasOne(ReviewEdit::class);
    }

    public function votes()
    {
        return $this->hasMany(ReviewVote::class);
    }
}
