<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 使用者基本表
        Schema::create('users', function (Blueprint $table) {
            $table->id(); // 使用者主鍵ID
            $table->string('name'); // 使用者姓名
            $table->string('email')->unique(); // 使用者電子郵件，唯一值
            $table->timestamp('email_verified_at')->nullable(); // 驗證郵件時間
            $table->string('password'); // 密碼
            $table->string('phone')->nullable(); // 電話號碼，可為空
            $table->enum('role', ['admin', 'coach', 'member'])->default('member'); // 角色：管理員、教練、會員
            $table->boolean('is_active')->default(true); // 是否啟用
            $table->rememberToken(); // 記住我 token
            $table->timestamps(); // 建立與更新時間
        });

        // 管理員資訊表
        Schema::create('admin_profiles', function (Blueprint $table) {
            $table->id(); // 管理員資訊主鍵ID
            $table->foreignId('user_id')->constrained()->onDelete('cascade'); // 關聯 users 表
            $table->string('position')->nullable(); // 職位
            $table->string('department')->nullable(); // 部門
            $table->text('permissions')->nullable(); // 可使用JSON儲存權限
            $table->timestamps(); // 建立與更新時間
        });

        // 教練資訊表
        Schema::create('coach_profiles', function (Blueprint $table) {
            $table->id(); // 教練資訊主鍵ID
            $table->foreignId('user_id')->constrained()->onDelete('cascade'); // 關聯 users 表
            $table->text('bio')->nullable(); // 教練簡介
            $table->string('expertise')->nullable(); // 專長領域
            $table->string('certification')->nullable(); // 認證資訊
            $table->string('avatar')->nullable(); // 頭像
            $table->boolean('is_featured')->default(false); // 是否為特色教練
            $table->timestamps(); // 建立與更新時間
        });
        
        // 會員資訊表
        Schema::create('member_profiles', function (Blueprint $table) {
            $table->id(); // 會員資訊主鍵ID
            $table->foreignId('user_id')->constrained()->onDelete('cascade'); // 關聯 users 表
            $table->date('birthday')->nullable(); // 生日
            $table->enum('gender', ['male', 'female', 'other'])->nullable(); // 性別
            $table->text('address')->nullable(); // 地址
            $table->string('emergency_contact')->nullable(); // 緊急聯絡人
            $table->string('emergency_phone')->nullable(); // 緊急聯絡電話
            $table->timestamps(); // 建立與更新時間
        });
        
        // 會員方案表
        Schema::create('plans', function (Blueprint $table) {
            $table->id(); // 方案主鍵ID
            $table->string('name'); // 方案名稱
            $table->text('description')->nullable(); // 方案描述
            $table->decimal('price', 10, 2); // 價格
            $table->integer('duration_days'); // 天數
            $table->boolean('is_active')->default(true); // 是否啟用
            $table->timestamps(); // 建立與更新時間
        });
        
        // 會員訂閱表
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id(); // 訂閱主鍵ID
            $table->foreignId('user_id')->constrained()->onDelete('cascade'); // 關聯 users 表
            $table->foreignId('plan_id')->constrained(); // 關聯 plans 表
            $table->date('start_date'); // 訂閱開始日期
            $table->date('end_date'); // 訂閱結束日期
            $table->enum('status', ['active', 'expired', 'cancelled'])->default('active'); // 訂閱狀態
            $table->timestamps(); // 建立與更新時間
        });
        
        // 教練與會員關聯表
        Schema::create('coach_member', function (Blueprint $table) {
            $table->id(); // 關聯主鍵ID
            $table->foreignId('coach_id')->constrained('users'); // 關聯教練（users表）
            $table->foreignId('member_id')->constrained('users'); // 關聯會員（users表）
            $table->timestamps(); // 建立與更新時間
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('coach_member');
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('plans');
        Schema::dropIfExists('member_profiles');
        Schema::dropIfExists('coach_profiles');
        Schema::dropIfExists('admin_profiles');
        Schema::dropIfExists('users');
    }
};