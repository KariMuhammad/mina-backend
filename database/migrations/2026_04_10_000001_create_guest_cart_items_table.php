<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guest_cart_items', function (Blueprint $table) {
            $table->id();
            $table->string('guest_id', 191)->index();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->integer('quantity')->default(1);
            $table->timestamps();

            $table->unique(['guest_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guest_cart_items');
    }
};

