<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('provider_profiles', function (Blueprint $table) {
            $table->string('verification_status', 20)->default('unsubmitted')->after('is_verified')
                ->comment('教練驗證狀態：unsubmitted / pending / approved / rejected');
            $table->text('rejection_reason')->nullable()->after('verification_status');
        });

        // 既有資料轉換：已驗證視為審核通過，未驗證回到未送審
        DB::table('provider_profiles')->where('is_verified', true)->update(['verification_status' => 'approved']);
        DB::table('provider_profiles')->where('is_verified', false)->update(['verification_status' => 'unsubmitted']);

        Schema::table('provider_profiles', function (Blueprint $table) {
            $table->dropColumn('is_verified');
        });
    }

    public function down(): void
    {
        Schema::table('provider_profiles', function (Blueprint $table) {
            $table->boolean('is_verified')->default(false);
        });

        DB::table('provider_profiles')->where('verification_status', 'approved')->update(['is_verified' => true]);

        Schema::table('provider_profiles', function (Blueprint $table) {
            $table->dropColumn(['verification_status', 'rejection_reason']);
        });
    }
};
