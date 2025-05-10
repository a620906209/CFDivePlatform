<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MemberProfile extends Model
{
    use HasFactory;

    /**
     * 與模型關聯的資料表
     *
     * @var string
     */
    protected $table = 'member_profiles';

    /**
     * 可以被批量賦值的屬性
     *
     * @var array
     */
    protected $fillable = [
        'user_id',      // 這個欄位必須包含在這裡
        'birthday',
        'gender',
        'address',
        'emergency_contact',
        'emergency_phone',
    ];

    /**
     * 應該被轉換的屬性
     *
     * @var array
     */
    protected $casts = [
        'birthday' => 'date',
    ];

    /**
     * 獲取擁有此會員資料的用戶
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 獲取會員的訂閱記錄
     */
    public function subscriptions()
    {
        return $this->hasMany(Subscription::class, 'user_id', 'user_id');
    }

    /**
     * 獲取會員的活躍訂閱
     */
    public function activeSubscription()
    {
        return $this->hasOne(Subscription::class, 'user_id', 'user_id')
                    ->where('status', 'active')
                    ->where('end_date', '>=', now())
                    ->latest();
    }

    /**
     * 獲取會員的教練
     */
    public function coaches()
    {
        return $this->hasManyThrough(
            CoachProfile::class,
            'coach_member',
            'member_id',
            'user_id',
            'user_id',
            'coach_id'
        );
    }

    /**
     * 檢查會員是否有活躍訂閱
     *
     * @return bool
     */
    public function hasActiveSubscription()
    {
        return $this->activeSubscription()->exists();
    }

    /**
     * 取得會員年齡
     *
     * @return int|null
     */
    public function getAge()
    {
        if (!$this->birthday) {
            return null;
        }
        
        return now()->diffInYears($this->birthday);
    }

    /**
     * 設定會員生日
     *
     * @param string|null $date
     * @return void
     */
    public function setBirthday($date)
    {
        $this->birthday = $date;
        $this->save();
    }

    /**
     * 更新會員緊急聯絡資訊
     *
     * @param string $contact
     * @param string $phone
     * @return void
     */
    public function updateEmergencyContact($contact, $phone)
    {
        $this->emergency_contact = $contact;
        $this->emergency_phone = $phone;
        $this->save();
    }
}