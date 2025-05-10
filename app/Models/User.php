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
     * 判斷用戶是否為教練
     */
    /**
     * 判斷用戶是否為教練
     * @return bool
     */
    public function isCoach()
    {
        return $this->role === 'coach';
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
     * 獲取用戶的教練資料
     */
    /**
     * 取得用戶的教練詳細資料（關聯 coach_profiles）
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function coachProfile()
    {
        return $this->hasOne(CoachProfile::class);
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
        } elseif ($this->isCoach()) {
            return $this->coachProfile;
        } else {
            return $this->memberProfile;
        }
    }

    /**
     * 獲取教練的會員 (僅適用於教練角色)
     */
    /**
     * 取得教練所帶的會員（僅教練角色適用）
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany|null
     */
    public function members()
    {
        if (!$this->isCoach()) {
            return null;
        }
        
        return $this->belongsToMany(User::class, 'coach_member', 'coach_id', 'member_id')
                    ->where('role', 'member');
    }

    /**
     * 獲取會員的教練 (僅適用於會員角色)
     */
    /**
     * 取得會員對應的教練（僅會員角色適用）
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany|null
     */
    public function coaches()
    {
        if (!$this->isMember()) {
            return null;
        }
        
        return $this->belongsToMany(User::class, 'coach_member', 'member_id', 'coach_id')
                    ->where('role', 'coach');
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