<?php

namespace App\Events;

use App\Models\Order;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderUpdated implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    /**
     * @param  Order  $order
     * @param  array<int, array<string, mixed>>  $historyEntries  Changed fields with old/new values
     */
    public function __construct(
        public readonly Order $order,
        public readonly array $historyEntries,
    ) {}
}
