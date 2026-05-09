<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DivingOffer extends Model
{
    public $timestamps = false;

    protected $table = 'diving_offers';

    protected $fillable = [
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
    ];

    protected $casts = [
        'badges' => 'array',
        'rating' => 'float',
        'price'  => 'integer',
        'reviews'=> 'integer',
    ];
}
