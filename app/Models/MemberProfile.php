<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @OA\Schema(
 *     schema="MemberProfile",
 *     title="會員個人資料",
 *     description="會員的詳細個人資料",
 *     @OA\Property(property="id", type="integer", format="int64", example=1, description="資料ID"),
 *     @OA\Property(property="user_id", type="integer", format="int64", example=1, description="關聯的使用者ID"),
 *     @OA\Property(property="birthday", type="string", format="date", example="1990-01-01", description="生日"),
 *     @OA\Property(property="gender", type="string", enum={"male", "female", "other"}, example="male", description="性別"),
 *     @OA\Property(property="address", type="string", example="台北市信義區某街123號", description="地址"),
 *     @OA\Property(property="emergency_contact", type="string", example="王大明", description="緊急聯絡人"),
 *     @OA\Property(property="emergency_phone", type="string", example="0987654321", description="緊急聯絡電話"),
 *     @OA\Property(property="created_at", type="string", format="date-time", example="2023-01-01T00:00:00.000000Z", description="創建時間"),
 *     @OA\Property(property="updated_at", type="string", format="date-time", example="2023-01-01T00:00:00.000000Z", description="更新時間")
 * )
 */
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
     * 獲取會員的服務提供者
     */
    public function providers()
    {
        return $this->hasManyThrough(
            ProviderProfile::class,
            'provider_member',
            'member_id',
            'user_id',
            'user_id',
            'provider_id'
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