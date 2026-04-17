<?php

namespace App\Services;

use App\Events\OrderUpdated;
use App\Models\Order;
use App\Models\OrderHistory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AdminOrderUpdateService
{
    /** Canonical status values (keyed by lowercase alias) */
    private const STATUS_MAP = [
        'pending'    => 'Pending',
        'new'        => 'Pending',
        'processing' => 'Processing',
        'processed'  => 'Processing',
        'shipped'    => 'Processing',
        'completed'  => 'Completed',
        'delivered'  => 'Completed',
        'done'       => 'Completed',
        'cancelled'  => 'Cancelled',
        'canceled'   => 'Cancelled',
    ];

    /** Canonical payment_status values (keyed by lowercase alias) */
    private const PAYMENT_STATUS_MAP = [
        'unpaid'  => 'unpaid',
        'pending' => 'unpaid',  // map legacy 'pending' → 'unpaid'
        'paid'    => 'paid',
    ];

    /** Allowed payment_method values */
    private const ALLOWED_PAYMENT_METHODS = ['cod', 'none', 'card', 'wallet', 'bank_transfer'];

    /**
     * Atomically update allowed order fields and record one audit row per changed field.
     *
     * @param  array<string, mixed>  $data   Validated input (only present keys are processed)
     * @param  string|null           $adminNote  Optional admin note stored on every history row
     * @return array{order: Order, history: list<array<string, mixed>>}
     */
    public function update(Order $order, array $data, int $adminId, ?string $adminNote = null): array
    {
        $historyEntries = [];

        DB::transaction(function () use ($order, $data, $adminId, $adminNote, &$historyEntries) {
            // Re-fetch with a row-level lock to prevent concurrent mutations
            /** @var Order $locked */
            $locked = Order::query()->lockForUpdate()->findOrFail($order->id);

            $toSave   = [];  // [ field => normalised_new_value ]
            $toAudit  = [];  // [ field => ['old' => ..., 'new' => ...] ]

            // --- status ---
            if (array_key_exists('status', $data) && $data['status'] !== null) {
                $normalized = self::STATUS_MAP[strtolower((string) $data['status'])]
                    ?? ucfirst(strtolower((string) $data['status']));
                if ($normalized !== $locked->status) {
                    $toSave['status']  = $normalized;
                    $toAudit['status'] = ['old' => $locked->status, 'new' => $normalized];
                }
            }

            // --- payment_status ---
            if (array_key_exists('payment_status', $data) && $data['payment_status'] !== null) {
                $ps = strtolower((string) $data['payment_status']);
                $normalized = self::PAYMENT_STATUS_MAP[$ps] ?? $ps;
                if ($normalized !== $locked->payment_status) {
                    $toSave['payment_status']  = $normalized;
                    $toAudit['payment_status'] = ['old' => $locked->payment_status, 'new' => $normalized];
                }
            }

            // --- payment_method ---
            if (array_key_exists('payment_method', $data) && $data['payment_method'] !== null) {
                $pm = strtolower((string) $data['payment_method']);
                if (in_array($pm, self::ALLOWED_PAYMENT_METHODS, true) && $pm !== $locked->payment_method) {
                    $toSave['payment_method']  = $pm;
                    $toAudit['payment_method'] = ['old' => $locked->payment_method, 'new' => $pm];
                }
            }

            // --- tracking_number ---
            if (array_key_exists('tracking_number', $data)) {
                $tn = $data['tracking_number'] !== null ? trim((string) $data['tracking_number']) : null;
                if ($tn !== $locked->tracking_number) {
                    $toSave['tracking_number']  = $tn;
                    $toAudit['tracking_number'] = ['old' => $locked->tracking_number, 'new' => $tn];
                }
            }

            // --- notes ---
            if (array_key_exists('notes', $data)) {
                $noteVal = $data['notes'] !== null ? trim((string) $data['notes']) : null;
                if ($noteVal !== $locked->notes) {
                    $toSave['notes']  = $noteVal;
                    $toAudit['notes'] = ['old' => $locked->notes, 'new' => $noteVal];
                }
            }

            if (empty($toSave)) {
                return; // Nothing changed — no write, no history row
            }

            // Apply changes and save in one shot
            $locked->fill($toSave);
            $locked->save();

            // Insert one history row per changed field
            $now = now();
            foreach ($toAudit as $field => ['old' => $old, 'new' => $new]) {
                $entry = OrderHistory::query()->create([
                    'order_id'   => $locked->id,
                    'admin_id'   => $adminId,
                    'field'      => $field,
                    'old_value'  => $old !== null ? (string) $old : null,
                    'new_value'  => $new !== null ? (string) $new : null,
                    'notes'      => $adminNote,
                    'created_at' => $now,
                ]);

                $historyEntries[] = [
                    'field'      => $entry->field,
                    'old_value'  => $entry->old_value,
                    'new_value'  => $entry->new_value,
                    'admin_id'   => $entry->admin_id,
                    'created_at' => $entry->created_at?->toIso8601String(),
                ];
            }

            // Sync the original $order instance so the caller gets fresh data
            $order->setRawAttributes($locked->getAttributes());
            $order->syncOriginal();

            Log::info('admin.order.updated', [
                'order_id' => $locked->id,
                'admin_id' => $adminId,
                'fields'   => array_keys($toAudit),
            ]);
        });

        // Reload relations needed by formatter (runs outside transaction, safe)
        $order->loadMissing(['orderItems.product', 'address']);

        // Dispatch after commit — no listeners required; safe to call with none
        OrderUpdated::dispatch($order, $historyEntries);

        return ['order' => $order, 'history' => $historyEntries];
    }
}
