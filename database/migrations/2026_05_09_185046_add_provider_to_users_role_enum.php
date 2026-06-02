<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('admin', 'coach', 'member', 'provider') NOT NULL DEFAULT 'member'");
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('admin', 'coach', 'member') NOT NULL DEFAULT 'member'");
    }
};
