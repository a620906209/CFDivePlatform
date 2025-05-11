<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// 該模型已棄用，請使用ProviderProfile模型
class CoachProfile extends Model
{
    /**
     * 可以批量分配的屬性
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'bio',
        'expertise',
        'certification',
        'experience',
        'rating',
        'availability'
    ];

    /**
     * 與用戶的關聯
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
