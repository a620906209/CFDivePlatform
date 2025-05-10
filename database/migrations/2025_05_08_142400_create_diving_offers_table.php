<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('diving_offers', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->timestampTz('created_at')->nullable();
            $table->string('title');
            $table->string('location');
            $table->string('spot');
            $table->float('rating', 2, 1)->default(0);
            $table->integer('reviews')->default(0);
            $table->integer('price')->default(0);
            $table->text('badges')->nullable(); // 可存 json 或逗號分隔字串
            $table->text('description')->nullable();
            $table->string('tag')->nullable();   // 可存單一或逗號分隔
            $table->string('region')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('diving_offers');
    }
};
