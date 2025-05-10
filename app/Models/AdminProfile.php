<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AdminProfile extends Model
{
    use HasFactory;

    /**
     * 與模型關聯的資料表
     *
     * @var string
     */
    protected $table = 'admin_profiles';

    /**
     * 可以被批量賦值的屬性
     *
     * @var array
     */
    protected $fillable = [
        'user_id',
        'position',
        'department',
        'permissions',
    ];

    /**
     * 應該被轉換的屬性
     *
     * @var array
     */
    protected $casts = [
        'permissions' => 'array',
    ];

    /**
     * 獲取擁有此管理員資料的用戶
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 檢查管理員是否擁有特定權限
     *
     * @param string $permission
     * @return bool
     */
    public function hasPermission($permission)
    {
        if (empty($this->permissions)) {
            return false;
        }

        return in_array($permission, $this->permissions);
    }

    /**
     * 添加權限給管理員
     *
     * @param string $permission
     * @return void
     */
    public function addPermission($permission)
    {
        $permissions = $this->permissions ?? [];
        
        if (!in_array($permission, $permissions)) {
            $permissions[] = $permission;
            $this->permissions = $permissions;
            $this->save();
        }
    }

    /**
     * 移除管理員的權限
     *
     * @param string $permission
     * @return void
     */
    public function removePermission($permission)
    {
        if (empty($this->permissions)) {
            return;
        }

        $permissions = array_filter($this->permissions, function($p) use ($permission) {
            return $p !== $permission;
        });

        $this->permissions = array_values($permissions);
        $this->save();
    }

    /**
     * 設定多個權限
     *
     * @param array $permissions
     * @return void
     */
    public function setPermissions(array $permissions)
    {
        $this->permissions = $permissions;
        $this->save();
    }

    /**
     * 清除所有權限
     *
     * @return void
     */
    public function clearPermissions()
    {
        $this->permissions = [];
        $this->save();
    }

    /**
     * 獲取所有權限
     *
     * @return array
     */
    public function getPermissions()
    {
        return $this->permissions ?? [];
    }
}