<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateOrderPaymentRequest;
use App\Http\Requests\Admin\UpdateOrderRequest;
use App\Http\Requests\UpdateOrderStatusRequest;
use App\Models\Order;
use App\Services\AdminOrderUpdateService;
use App\Services\WhatsAppService;
use App\Support\CustomerOrderFormatter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function __construct(
        private readonly AdminOrderUpdateService $orderUpdateService,
        private readonly WhatsAppService $whatsappService,
    ) {}

    // -----------------------------------------------------------------------
    // Listing
    // -----------------------------------------------------------------------

    public function index(Request $request): JsonResponse
    {
        $query = Order::query()
            ->with([
                'user:id,name,email,phone',
                'orderItems.product:id,name,price,image,image_path',
            ])
            ->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->get('status'));
        }

        if ($request->filled('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('id', 'like', "%{$search}%")
                  ->orWhere('customer_name', 'like', "%{$search}%")
                  ->orWhere('customer_phone', 'like', "%{$search}%")
                  ->orWhere('guest_name', 'like', "%{$search}%")
                  ->orWhere('guest_phone', 'like', "%{$search}%")
                  ->orWhereHas('user', function ($q2) use ($search) {
                      $q2->where('name', 'like', "%{$search}%")
                         ->orWhere('phone', 'like', "%{$search}%")
                         ->orWhere('email', 'like', "%{$search}%");
                  });
            });
        }

        if ($request->filled('date')) {
            $query->whereDate('created_at', $request->get('date'));
        }

        $perPage = $request->get('per_page', 50);
        $paginator = $query->paginate($perPage);

        $items = $paginator->getCollection()
            ->map(fn (Order $order) => CustomerOrderFormatter::order($order))
            ->values()
            ->all();

        return response()->json([
            'data'         => $items,
            'total'        => $paginator->total(),
            'current_page' => $paginator->currentPage(),
            'last_page'    => $paginator->lastPage(),
        ]);
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

    // -----------------------------------------------------------------------
    // Stats — GET /api/admin/orders/stats
    // -----------------------------------------------------------------------

    public function stats(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => [
                'total_orders'   => Order::count(),
                'pending_orders'    => Order::where('status', 'Pending')->count(),
                'processing'        => Order::where('status', 'Processing')->count(),
                'preparing'         => Order::where('status', 'Preparing')->count(),
                'out_for_delivery'  => Order::where('status', 'Out_for_Delivery')->count(),
                'completed'         => Order::where('status', 'Completed')->count(),
                'cancelled'         => Order::where('status', 'Cancelled')->count(),
                'total_revenue'  => (float) Order::whereNotIn('status', ['Cancelled'])->sum('total_price'),
                'today_orders'   => Order::whereDate('created_at', today())->count(),
                'today_revenue'  => (float) Order::whereDate('created_at', today())
                    ->whereNotIn('status', ['Cancelled'])
                    ->sum('total_price'),
            ],
        ]);
    }

    // -----------------------------------------------------------------------
    // Send via WhatsApp Cloud API — POST /api/admin/orders/{id}/send-whatsapp
    // -----------------------------------------------------------------------

    public function sendWhatsApp(int $id): JsonResponse
    {
        $order = Order::findOrFail($id);

        $result = $this->whatsappService->sendOrder($order);

        if ($result['success']) {
            $order->whatsapp_sent    = true;
            $order->whatsapp_sent_at = now();
            $order->save();
        }

        return response()->json($result, $result['success'] ? 200 : 500);
    }

    // -----------------------------------------------------------------------
    // Mark WhatsApp sent — POST /api/admin/orders/{id}/whatsapp-sent
    // -----------------------------------------------------------------------

    public function markWhatsappSent(int $id): JsonResponse
    {
        $order = Order::findOrFail($id);
        $order->whatsapp_sent    = true;
        $order->whatsapp_sent_at = now();
        $order->save();

        return response()->json([
            'success' => true,
            'message' => 'WhatsApp marked as sent.',
        ]);
    }
}
