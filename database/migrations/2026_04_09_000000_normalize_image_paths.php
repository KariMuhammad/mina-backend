<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('avatar_path')->nullable()->after('avatar');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->string('image_path')->nullable()->after('image');
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->string('image_path')->nullable()->after('name');
        });

        Schema::table('banners', function (Blueprint $table) {
            $table->string('image_path')->nullable()->after('image_url');
        });

        // Optional: migrate backward compatible data
        /*
        DB::statement("UPDATE users SET avatar_path = avatar WHERE avatar IS NOT NULL AND avatar NOT LIKE 'http%'");
        DB::statement("UPDATE products SET image_path = image WHERE image IS NOT NULL AND image NOT LIKE 'http%'");
        DB::statement("UPDATE banners SET image_path = image_url WHERE image_url IS NOT NULL AND image_url NOT LIKE 'http%'");
        */
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('avatar_path');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('image_path');
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('image_path');
        });

        Schema::table('banners', function (Blueprint $table) {
            $table->dropColumn('image_path');
        });
    }
};
