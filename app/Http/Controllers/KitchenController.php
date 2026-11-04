<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\StoreSetting;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class KitchenController extends Controller
{
    /**
     * Display the Kitchen Display System (KDS) screen.
     */
    public function index(): Response
    {
        return Inertia::render('KitchenView', [
            'initialData' => $this->fetchKitchenData(),
        ]);
    }

    /**
     * Polling API for Kitchen orders.
     */
    public function getOrders(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->fetchKitchenData(),
        ]);
    }

    /**
     * Start cooking an order.
     */
    public function startCooking(Order $order): JsonResponse
    {
        $order->update([
            'kitchen_status' => 'cooking',
            'cooking_started_at' => $order->cooking_started_at ?? Carbon::now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => "Pesanan {$order->order_number} mulai dimasak.",
            'order' => $order->load(['items.product']),
        ]);
    }

    /**
     * Mark an order as ready for pickup.
     */
    public function markReady(Order $order): JsonResponse
    {
        $order->update([
            'kitchen_status' => 'ready',
            'ready_at' => Carbon::now(),
            'call_counter' => $order->call_counter + 1,
        ]);

        return response()->json([
            'success' => true,
            'message' => "Pesanan {$order->order_number} siap disajikan!",
            'order' => $order->load(['items.product']),
        ]);
    }

    /**
     * Mark an order as completed / served to customer.
     */
    public function markCompleted(Order $order): JsonResponse
    {
        $order->update([
            'kitchen_status' => 'completed',
            'completed_at' => Carbon::now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => "Pesanan {$order->order_number} selesai disajikan.",
            'order' => $order->load(['items.product']),
        ]);
    }

    /**
     * Recall / re-announce an order to the TV Display.
     */
    public function recallOrder(Order $order): JsonResponse
    {
        $order->update([
            'call_counter' => $order->call_counter + 1,
            'ready_at' => $order->ready_at ?? Carbon::now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => "Pesanan {$order->order_number} dipanggil ke layar TV antrean.",
            'order' => $order->load(['items.product']),
        ]);
    }

    /**
     * Fetch formatted kitchen data.
     */
    private function fetchKitchenData(): array
    {
        $today = Carbon::today();

        // Active kitchen orders (paid orders of today, not yet completed or completed recently in last 20 min)
        $orders = Order::query()
            ->whereDate('created_at', $today)
            ->where('status', 'paid')
            ->where(function ($query) {
                $query->whereIn('kitchen_status', ['queued', 'cooking', 'ready'])
                    ->orWhere(function ($q) {
                        $q->where('kitchen_status', 'completed')
                            ->where('completed_at', '>=', Carbon::now()->subMinutes(20));
                    });
            })
            ->with(['items.product'])
            ->orderByRaw("
                CASE 
                    WHEN kitchen_status = 'cooking' THEN 1
                    WHEN kitchen_status = 'queued' THEN 2
                    WHEN kitchen_status = 'ready' THEN 3
                    ELSE 4
                END
            ")
            ->orderBy('created_at', 'asc')
            ->get();

        $counts = [
            'queued' => Order::whereDate('created_at', $today)->where('status', 'paid')->where('kitchen_status', 'queued')->count(),
            'cooking' => Order::whereDate('created_at', $today)->where('status', 'paid')->where('kitchen_status', 'cooking')->count(),
            'ready' => Order::whereDate('created_at', $today)->where('status', 'paid')->where('kitchen_status', 'ready')->count(),
            'completed_today' => Order::whereDate('created_at', $today)->where('status', 'paid')->where('kitchen_status', 'completed')->count(),
        ];

        return [
            'orders' => $orders,
            'counts' => $counts,
            'store_settings' => StoreSetting::getAllAsMap(),
        ];
    }
}
