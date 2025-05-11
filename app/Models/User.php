<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * User 使用者模型
 *
 * 對應 users 資料表，並提供角色判斷、關聯資料取得等功能。
 *
 * @OA\Schema(
 *     schema="User",
 *     title="使用者",
 *     description="使用者資料模型",
 *     @OA\Property(property="id", type="integer", format="int64", example=1, description="使用者ID"),
 *     @OA\Property(property="name", type="string", example="王小明", description="使用者姓名"),
 *     @OA\Property(property="email", type="string", format="email", example="user@example.com", description="電子郵件"),
 *     @OA\Property(property="phone", type="string", example="0912345678", description="電話號碼"),
 *     @OA\Property(property="role", type="string", enum={"member", "provider", "admin"}, example="member", description="角色"),
 *     @OA\Property(property="is_active", type="boolean", example=true, description="是否啟用"),
 *     @OA\Property(property="email_verified_at", type="string", format="date-time", example="2023-01-01T00:00:00.000000Z", description="電子郵件驗證時間"),
 *     @OA\Property(property="created_at", type="string", format="date-time", example="2023-01-01T00:00:00.000000Z", description="創建時間"),
 *     @OA\Property(property="updated_at", type="string", format="date-time", example="2023-01-01T00:00:00.000000Z", description="更新時間"),
 *     @OA\Property(property="memberProfile", type="object", ref="#/components/schemas/MemberProfile", description="會員詳細資料"),
 *     @OA\Property(property="providerProfile", type="object", ref="#/components/schemas/ProviderProfile", description="服務提供者詳細資料"),
 *     @OA\Property(property="adminProfile", type="object", ref="#/components/schemas/AdminProfile", description="管理員詳細資料")
 * )
 */
class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    /**
     * 可批次賦值的欄位（對應 users 資料表）
     * @var array<int, string>
     */
    protected $fillable = [
        'name',        // 姓名
        'email',       // 電子郵件
        'password',    // 密碼
        'phone',       // 電話
        'role',        // 角色
        'is_active',   // 是否啟用
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    /**
     * 隱藏於序列化時的欄位
     * @var array<int, string>
     */
    protected $hidden = [
        'password',         // 密碼
        'remember_token',   // 記住我 token
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    /**
     * 欄位型別轉換
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime', // 驗證時間
        'password' => 'hashed',           // 密碼雜湊
    ];

    /**
     * 判斷用戶是否為管理員
     */
    /**
     * 判斷用戶是否為管理員
     * @return bool
     */
    public function isAdmin()
    {
        return $this->role === 'admin';
    }

    /**
     * 判斷用戶是否為服務提供者
     */
    /**
     * 判斷用戶是否為服務提供者
     * @return bool
     */
    public function isProvider()
    {
        return $this->role === 'provider';
    }

    /**
     * 判斷用戶是否為一般會員
     */
    /**
     * 判斷用戶是否為一般會員
     * @return bool
     */
    public function isMember()
    {
        return $this->role === 'member';
    }

    /**
     * 獲取用戶的管理員資料
     */
    /**
     * 取得用戶的管理員詳細資料（關聯 admin_profiles）
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function adminProfile()
    {
        return $this->hasOne(AdminProfile::class);
    }

    /**
     * 獲取用戶的服務提供者資料
     */
    /**
     * 取得用戶的服務提供者詳細資料（關聯 provider_profiles）
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function providerProfile()
    {
        return $this->hasOne(ProviderProfile::class);
    }

    /**
     * 獲取用戶的會員資料
     */
    /**
     * 取得用戶的會員詳細資料（關聯 member_profiles）
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function memberProfile()
    {
        return $this->hasOne(MemberProfile::class);
    }

    /**
     * 獲取用戶的設定檔資料 (根據角色自動選擇)
     */
    /**
     * 取得用戶的詳細資料（依角色自動選擇對應 profile）
     * @return mixed
     */
    public function profile()
    {
        if ($this->isAdmin()) {
            return $this->adminProfile;
        } elseif ($this->isProvider()) {
            return $this->providerProfile;
        } else {
            return $this->memberProfile;
        }
    }

    /**
     * 獲取服務提供者的會員 (僅適用於服務提供者角色)
     */
    /**
     * 取得服務提供者所帶的會員（僅服務提供者角色適用）
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany|null
     */
    public function members()
    {
        if (!$this->isProvider()) {
            return null;
        }
        
        return $this->belongsToMany(User::class, 'provider_member', 'provider_id', 'member_id')
                    ->where('role', 'member');
    }

    /**
     * 獲取會員的服務提供者 (僅適用於會員角色)
     */
    /**
     * 取得會員對應的服務提供者（僅會員角色適用）
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany|null
     */
    public function providers()
    {
        if (!$this->isMember()) {
            return null;
        }
        
        return $this->belongsToMany(User::class, 'provider_member', 'member_id', 'provider_id')
                    ->where('role', 'provider');
    }

    /**
     * 獲取用戶的訂閱
     */
    /**
     * 取得用戶的所有訂閱紀錄
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * 獲取用戶目前有效的訂閱
     */
    /**
     * 取得用戶目前有效的訂閱（狀態為 active 且未過期）
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function activeSubscription()
    {
        return $this->hasOne(Subscription::class)
                    ->where('status', 'active')
                    ->where('end_date', '>=', now())
                    ->latest();
    }
}