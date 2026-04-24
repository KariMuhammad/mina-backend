<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('banners', function (Blueprint $table) {
            if (!Schema::hasIndex('banners', 'banners_is_active_order_index')) {
                $table->index(['is_active', 'order']);
            }
        });

        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasIndex('orders', 'orders_status_created_at_index')) {
                $table->index(['status', 'created_at']);
            }
        });

        Schema::table('app_settings', function (Blueprint $table) {
            if (!Schema::hasIndex('app_settings', 'app_settings_key_index')) {
                $table->index('key');
            }
        });
    }

    public function down()
    {
        Schema::table('banners', function (Blueprint $table) {
            $table->dropIndex(['is_active', 'order']);
        });
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['status', 'created_at']);
        });
        Schema::table('app_settings', function (Blueprint $table) {
            $table->dropIndex(['key']);
        });
    }
};
