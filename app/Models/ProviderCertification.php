<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class ProviderCertification extends Model
{
    protected $fillable = [
        'user_id',
        'image_path',
    ];

    protected static function booted(): void
    {
        static::deleting(function ($certification) {
            Storage::disk('public')->delete($certification->image_path);
        });
    }

    public function getUrlAttribute(): string
    {
        return Storage::disk('public')->url($this->image_path);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
