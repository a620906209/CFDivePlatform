<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provider_certifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete()->comment('教練 User id');
            $table->string('image_path')->comment('證照圖片相對路徑（public disk）');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_certifications');
    }
};
