<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateOrderPaymentRequest;
use App\Http\Requests\Admin\UpdateOrderRequest;
use App\Http\Requests\UpdateOrderStatusRequest;
use App\Models\Order;
use App\Services\AdminOrderUpdateService;
use App\Support\CustomerOrderFormatter;
use Illuminate\Http\JsonResponse;

class OrderController extends Controller
{
    public function __construct(private readonly AdminOrderUpdateService $orderUpdateService)
    {}

    // -----------------------------------------------------------------------
    // Listing
    // -----------------------------------------------------------------------

    public function index(): JsonResponse
    {
        $orders = Order::query()
            ->with([
                'user:id,name,email,phone',
                'orderItems.product:id,name,price,image,image_path',
            ])
            ->latest()
            ->get();

        return response()->json($orders);
    }

    // -----------------------------------------------------------------------
    // Full update  — PATCH /api/admin/orders/{id}
    // -----------------------------------------------------------------------

    public function update(UpdateOrderRequest $request, int $id): JsonResponse
    {
        $order = Order::findOrFail($id);

        /** @var \App\Models\User $admin */
        $admin = $request->user();

        $validated  = $request->validated();
        $adminNote  = isset($validated['notes']) ? (string) $validated['notes'] : null;

        $result = $this->orderUpdateService->update($order, $validated, $admin->id, $adminNote);

        return response()->json([
            'message' => 'Order updated successfully.',
            'order'   => CustomerOrderFormatter::order($result['order']),
            'history' => $result['history'],
        ]);
    }

    // -----------------------------------------------------------------------
    // Status-only — PATCH /api/admin/orders/{id}/status
    // -----------------------------------------------------------------------

    public function updateStatus(UpdateOrderStatusRequest $request, int $id): JsonResponse
    {
        $order = Order::findOrFail($id);

        /** @var \App\Models\User $admin */
        $admin = $request->user();

        $result = $this->orderUpdateService->update(
            $order,
            ['status' => $request->validated('status')],
            $admin->id
        );

        return response()->json([
            'message' => 'Order status updated.',
            'order'   => CustomerOrderFormatter::order($result['order']),
            'history' => $result['history'],
        ]);
    }

    // -----------------------------------------------------------------------
    // Payment-only — PATCH /api/admin/orders/{id}/payment
    // -----------------------------------------------------------------------

    public function updatePayment(UpdateOrderPaymentRequest $request, int $id): JsonResponse
    {
        $order = Order::findOrFail($id);

        /** @var \App\Models\User $admin */
        $admin = $request->user();

        $result = $this->orderUpdateService->update(
            $order,
            $request->validated(),
            $admin->id
        );

        return response()->json([
            'message' => 'Order payment updated.',
            'order'   => CustomerOrderFormatter::order($result['order']),
            'history' => $result['history'],
        ]);
    }

    // -----------------------------------------------------------------------
    // Delete
    // -----------------------------------------------------------------------

    public function destroy(int $id): JsonResponse
    {
        $order = Order::findOrFail($id);
        $order->delete();

        return response()->json(['message' => 'Order deleted successfully.']);
    }
}
