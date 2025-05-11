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
        Schema::create('provider_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('business_name')->nullable();
            $table->string('business_license')->nullable()->comment('營業執照號碼');
            $table->text('description')->nullable()->comment('業者描述');
            $table->string('contact_person')->nullable()->comment('聯絡人');
            $table->string('contact_phone')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('address')->nullable()->comment('營業地址');
            $table->text('dive_sites')->nullable()->comment('提供的潛點');
            $table->text('services')->nullable()->comment('提供的服務，例如：訓練課程、裝備出租、潛導服務等');
            $table->text('certifications')->nullable()->comment('業者相關認證');
            $table->text('facilities')->nullable()->comment('設施，如壓縮空氣設備、沖洗區等');
            $table->string('business_hours')->nullable()->comment('營業時間');
            $table->boolean('is_verified')->default(false)->comment('是否通過平台驗證');
            $table->float('rating')->default(0)->comment('評分');
            $table->string('website')->nullable()->comment('官方網站');
            $table->string('social_media')->nullable()->comment('社群媒體連結');
            $table->string('logo_url')->nullable()->comment('業者標誌URL');
            $table->string('banner_url')->nullable()->comment('業者橫幅URL');
            $table->boolean('is_active')->default(true)->comment('是否啟用');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('provider_profiles');
    }
};
