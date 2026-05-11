<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReviewVote extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'review_id',
        'member_id',
        'created_at',
    ];

    public function review()
    {
        return $this->belongsTo(Review::class);
    }

    public function member()
    {
        return $this->belongsTo(User::class, 'member_id');
    }
}
