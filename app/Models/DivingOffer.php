<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
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

    /**
     * 公開端點可見性：未通過審核（approved）教練的課程不對外曝光。
     * provider_id 為 null 的課程（平台自有資料）不受此限制。
     */
    public function scopeVisibleToPublic(Builder $query): Builder
    {
        return $query->where(function (Builder $visible) {
            $visible->whereNull('provider_id')
                ->orWhereHas('provider.providerProfile', fn (Builder $profile) => $profile->where('verification_status', \App\Enums\VerificationStatus::Approved->value));
        });
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
