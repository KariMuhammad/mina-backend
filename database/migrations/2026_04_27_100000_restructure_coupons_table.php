<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coupons', function (Blueprint $table) {
            $table->enum('type', ['percent', 'fixed'])->default('percent')->after('code');
            $table->decimal('value', 8, 2)->default(0)->after('type');
            $table->decimal('min_order', 8, 2)->default(0)->after('value');
            $table->integer('max_uses')->nullable()->after('min_order');
            $table->integer('used_count')->default(0)->after('max_uses');
            $table->timestamp('expires_at')->nullable()->after('used_count');
        });

        // Migrate data from old columns to new ones
        DB::statement("UPDATE coupons SET type = 'percent', value = discount_percent WHERE discount_percent > 0");
        DB::statement("UPDATE coupons SET type = 'fixed', value = discount_amount WHERE discount_percent = 0 AND discount_amount > 0");
        DB::statement("UPDATE coupons SET min_order = minimum_order");
        DB::statement("UPDATE coupons SET expires_at = CASE WHEN valid_until IS NOT NULL THEN TIMESTAMP(valid_until) ELSE NULL END");

        Schema::table('coupons', function (Blueprint $table) {
            $table->dropColumn(['discount_amount', 'discount_percent', 'minimum_order', 'valid_until']);
        });
    }

    public function down(): void
    {
        Schema::table('coupons', function (Blueprint $table) {
            $table->decimal('discount_amount', 8, 2)->default(0)->after('code');
            $table->decimal('discount_percent', 5, 2)->default(0)->after('discount_amount');
            $table->decimal('minimum_order', 8, 2)->default(0)->after('discount_percent');
            $table->date('valid_until')->nullable()->after('minimum_order');
        });

        DB::statement("UPDATE coupons SET discount_percent = value WHERE type = 'percent'");
        DB::statement("UPDATE coupons SET discount_amount = value WHERE type = 'fixed'");
        DB::statement("UPDATE coupons SET minimum_order = min_order");
        DB::statement("UPDATE coupons SET valid_until = DATE(expires_at) WHERE expires_at IS NOT NULL");

        Schema::table('coupons', function (Blueprint $table) {
            $table->dropColumn(['type', 'value', 'min_order', 'max_uses', 'used_count', 'expires_at']);
        });
    }
};
