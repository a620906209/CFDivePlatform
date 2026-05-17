<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class DivingOffer extends Model
{
    public $timestamps = false;

    protected $table = 'diving_offers';

    protected $fillable = [
        'provider_id',
        'title',
        'location',
        'spot',
        'rating',
        'reviews',
        'price',
        'badges',
        'description',
        'tag',
        'region',
        'cover_image',
    ];

    protected $casts = [
        'badges' => 'array',
        'rating' => 'float',
        'price'  => 'integer',
        'reviews'=> 'integer',
    ];

    protected static function booted(): void
    {
        static::deleting(function ($offer) {
            Storage::disk('public')->deleteDirectory("offers/{$offer->id}");
        });
    }

    public function getCoverImageUrlAttribute(): ?string
    {
        return $this->cover_image
            ? Storage::disk('public')->url($this->cover_image)
            : null;
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'provider_id');
    }

    public function schedules()
    {
        return $this->hasMany(CourseSchedule::class, 'diving_offer_id');
    }

    public function courseImages()
    {
        return $this->hasMany(CourseImage::class)->orderBy('sort_order');
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }
}
