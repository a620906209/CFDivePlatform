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
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('diving_offer_id')->constrained('diving_offers')->cascadeOnDelete();
            $table->foreignId('member_id')->constrained('users')->cascadeOnDelete();
            $table->tinyInteger('rating')->unsigned();
            $table->text('comment');
            $table->unsignedInteger('helpful_count')->default(0);
            $table->boolean('is_edited')->default(false);
            $table->timestamps();

            $table->unique(['member_id', 'diving_offer_id']);
            $table->index(['diving_offer_id', 'helpful_count'], 'idx_reviews_helpful');
            $table->index(['diving_offer_id', 'rating'],        'idx_reviews_rating');
            $table->index(['diving_offer_id', 'created_at'],   'idx_reviews_newest');
            $table->index(['member_id', 'diving_offer_id'],    'idx_reviews_member_offer');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
