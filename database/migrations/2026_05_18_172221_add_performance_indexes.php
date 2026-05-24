<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->index(['notifiable_type', 'notifiable_id', 'read_at'], 'notifications_notifiable_read_at_index');
        });

        Schema::table('diving_offers', function (Blueprint $table) {
            $table->index('provider_id', 'diving_offers_provider_id_index');
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropIndex('notifications_notifiable_read_at_index');
        });

        Schema::table('diving_offers', function (Blueprint $table) {
            $table->dropIndex('diving_offers_provider_id_index');
        });
    }
};
