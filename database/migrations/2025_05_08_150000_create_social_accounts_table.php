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
        Schema::create('social_accounts', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id')->comment('本地 user 對應 id');
            $table->string('provider')->comment('第三方登入來源，如 google');
            $table->string('provider_id')->comment('第三方平台的唯一識別碼');
            $table->string('provider_email')->nullable()->comment('第三方平台的 email');
            $table->string('provider_name')->nullable()->comment('第三方平台顯示名稱');
            $table->string('avatar')->nullable()->comment('第三方平台頭像網址');
            $table->text('access_token')->nullable()->comment('第三方 access token');
            $table->text('refresh_token')->nullable()->comment('第三方 refresh token');
            $table->integer('expires_in')->nullable()->comment('token 有效秒數');
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->unique(['provider', 'provider_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('social_accounts');
    }
};
