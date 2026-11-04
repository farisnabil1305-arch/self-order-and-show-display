<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\StoreSetting;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

class DisplayController extends Controller
{
    /**
     * Display the TV Queue Screen.
     */
    public function index(): Response
    {
        return Inertia::render('DisplayView', [
            'initialData' => $this->fetchDisplayData(),
        ]);
    }

    /**
     * Polling endpoint for the TV Queue Screen.
     */
    public function getQueue(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->fetchDisplayData(),
        ]);
    }

    /**
     * Fetch active queue data for the TV Display.
     */
    private function fetchDisplayData(): array
    {
        $today = Carbon::today();

        // Preparing (Sedang Dimasak / Disiapkan)
        $preparing = Order::query()
            ->whereDate('created_at', $today)
            ->where('status', 'paid')
            ->whereIn('kitchen_status', ['queued', 'cooking'])
            ->select('id', 'order_number', 'customer_name', 'table_number', 'order_type', 'kitchen_status', 'created_at')
            ->orderBy('created_at', 'asc')
            ->take(16)
            ->get();

        // Ready for Pickup (Siap Diambil)
        $ready = Order::query()
            ->whereDate('created_at', $today)
            ->where('status', 'paid')
            ->where('kitchen_status', 'ready')
            ->select('id', 'order_number', 'customer_name', 'table_number', 'order_type', 'kitchen_status', 'ready_at', 'call_counter')
            ->orderBy('ready_at', 'desc')
            ->take(12)
            ->get();

        // Latest called order (if called recently within today)
        $latestCall = Order::query()
            ->whereDate('created_at', $today)
            ->where('status', 'paid')
            ->where('kitchen_status', 'ready')
            ->where('call_counter', '>', 0)
            ->select('id', 'order_number', 'customer_name', 'table_number', 'order_type', 'ready_at', 'call_counter')
            ->orderBy('ready_at', 'desc')
            ->first();

        return [
            'preparing' => $preparing,
            'ready' => $ready,
            'latest_call' => $latestCall,
            'store_settings' => StoreSetting::getAllAsMap(),
            'server_time' => Carbon::now()->toIso8601String(),
        ];
    }
}
