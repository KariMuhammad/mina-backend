<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\Order;
use App\Support\CustomerOrderFormatter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerOrderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $paginator = $request->user()
            ->orders()
            ->withCount('orderItems')
            ->latest()
            ->paginate((int) $request->query('per_page', 15));

        $paginator->setCollection(
            $paginator->getCollection()->map(
                fn (Order $o) => CustomerOrderFormatter::summary($o)
            )
        );

        return response()->json($paginator);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $order = Order::query()
            ->with(['orderItems.product', 'address'])
            ->where('id', $id)
            ->first();

        if ($order === null) {
            return response()->json(['message' => 'Resource not found.'], 404);
        }

        if ((int) $order->user_id !== (int) $request->user()->id) {
            return response()->json(['message' => 'Access denied! This resource does not belong to you.'], 403);
        }

        $deliveryFee = (float) (AppSetting::where('key', 'delivery_price')->value('value') ?? 0);
        $total = round((float) $order->subtotal + $deliveryFee - (float) $order->discount_amount, 2);

        return response()->json([
            'order' => CustomerOrderFormatter::order($order),
            'items' => CustomerOrderFormatter::items($order),
            'delivery_fee' => $deliveryFee,
            'total' => $total,
        ]);
    }
}
