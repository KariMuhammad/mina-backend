<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Fill any null emails/phones with placeholder before making NOT NULL
        DB::table('users')->whereNull('email')->update(['email' => 'user_' . DB::raw('id') . '@placeholder.com']);
        DB::table('users')->whereNull('phone')->update(['phone' => '0000000000']);

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
