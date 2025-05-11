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
        // 創建 provider_member 表
        Schema::create('provider_member', function (Blueprint $table) {
            $table->id(); // 關聯主鍵ID
            $table->foreignId('provider_id')->constrained('users'); // 關聯服務提供者（users表）
            $table->foreignId('member_id')->constrained('users'); // 關聯會員（users表）
            $table->timestamps(); // 建立與更新時間
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 刪除 provider_member 表
        Schema::dropIfExists('provider_member');
    }
};
