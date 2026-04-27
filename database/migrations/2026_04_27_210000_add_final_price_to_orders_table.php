<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('final_price', 10, 2)->default(0)->after('discount_amount');
        });

        // Populate final_price from existing orders: total_price already includes discount
        \Illuminate\Support\Facades\DB::statement('UPDATE orders SET final_price = total_price WHERE final_price = 0');
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('final_price');
        });
    }
};
