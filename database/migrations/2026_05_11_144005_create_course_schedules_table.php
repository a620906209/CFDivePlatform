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
        Schema::create('course_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('diving_offer_id')->constrained('diving_offers')->cascadeOnDelete();
            $table->foreignId('provider_id')->constrained('users')->cascadeOnDelete();
            $table->date('scheduled_date');
            $table->time('start_time');
            $table->unsignedInteger('max_participants');
            $table->unsignedInteger('current_participants')->default(0);
            $table->string('status')->default('open');
            $table->timestamps();

            $table->index(['diving_offer_id', 'status', 'scheduled_date'], 'idx_offer_status_date');
            $table->index('provider_id', 'idx_provider_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('course_schedules');
    }
};
