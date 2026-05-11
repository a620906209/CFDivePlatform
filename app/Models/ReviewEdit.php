<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReviewEdit extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'review_id',
        'old_rating',
        'old_comment',
        'edited_at',
    ];

    protected $casts = [
        'old_rating' => 'integer',
        'edited_at'  => 'datetime',
    ];

    public function review()
    {
        return $this->belongsTo(Review::class);
    }
}
