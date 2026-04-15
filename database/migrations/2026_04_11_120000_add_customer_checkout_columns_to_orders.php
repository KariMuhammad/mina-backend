<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('payment_method', 16)->default('cod')->after('payment_status');
            $table->text('notes')->nullable()->after('payment_method');
        });

        $this->normalizeStatuses();

        Schema::table('orders', function (Blueprint $table) {
            $table->string('status', 32)->default('Pending')->change();
            $table->string('payment_status', 24)->default('unpaid')->change();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['payment_method', 'notes']);
        });
    }

    private function normalizeStatuses(): void
    {
        $statusMap = [
            'new' => 'Pending',
            'pending' => 'Pending',
            'processed' => 'Processing',
            'processing' => 'Processing',
            'shipped' => 'Processing',
            'paid' => 'Processing',
            'delivered' => 'Completed',
            'done' => 'Completed',
            'completed' => 'Completed',
            'cancelled' => 'Cancelled',
            'canceled' => 'Cancelled',
        ];

        foreach ($statusMap as $from => $to) {
            DB::table('orders')->where('status', $from)->update(['status' => $to]);
        }

        DB::table('orders')->whereNotIn('status', [
            'Pending', 'Processing', 'Completed', 'Cancelled',
        ])->update(['status' => 'Pending']);

        $paymentMap = [
            'pending' => 'unpaid',
        ];

        foreach ($paymentMap as $from => $to) {
            DB::table('orders')->where('payment_status', $from)->update(['payment_status' => $to]);
        }
    }
};
