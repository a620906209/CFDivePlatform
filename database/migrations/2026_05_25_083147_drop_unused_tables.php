<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('plans');
        Schema::dropIfExists('coach_member');
        Schema::dropIfExists('coach_profiles');
    }

    public function down(): void
    {
        // 這些表已確認廢棄，不提供還原
    }
};
