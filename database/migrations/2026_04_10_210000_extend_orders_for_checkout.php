<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->change();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->string('guest_id', 64)->nullable()->after('user_id')->index();
            $table->string('guest_name')->nullable()->after('guest_id');
            $table->string('guest_email')->nullable()->after('guest_name');
            $table->string('guest_phone', 32)->nullable()->after('guest_email');

            $table->foreignId('address_id')->nullable()->after('guest_phone')->constrained('addresses')->nullOnDelete();

            $table->decimal('subtotal', 10, 2)->default(0)->after('total_price');
            $table->decimal('discount_amount', 10, 2)->default(0)->after('subtotal');

            $table->foreignId('coupon_id')->nullable()->after('discount_amount')->constrained('coupons')->nullOnDelete();
            $table->string('coupon_code', 64)->nullable()->after('coupon_id');

            $table->string('payment_status', 24)->default('pending')->after('status');
            $table->json('shipping_address')->nullable()->after('payment_status');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->string('status', 32)->default('pending')->change();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['address_id']);
            $table->dropForeign(['coupon_id']);
            $table->dropColumn([
                'guest_id',
                'guest_name',
                'guest_email',
                'guest_phone',
                'address_id',
                'subtotal',
                'discount_amount',
                'coupon_id',
                'coupon_code',
                'payment_status',
                'shipping_address',
            ]);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable(false)->change();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }
};
