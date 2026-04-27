<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Backfill null values with safe defaults before applying NOT NULL
        DB::statement("UPDATE users SET email = CONCAT('user_', id, '@placeholder.com') WHERE email IS NULL");
        DB::statement("UPDATE users SET phone = '0000000000' WHERE phone IS NULL");
        DB::statement("UPDATE users SET password = '' WHERE password IS NULL");

        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable(false)->unique()->change();
            $table->string('phone')->nullable(false)->unique()->change();
            $table->string('password')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable()->unique()->change();
            $table->string('phone')->nullable()->unique()->change();
            $table->string('password')->nullable()->change();
        });
    }
};
