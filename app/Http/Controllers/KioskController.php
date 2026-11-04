<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\RestaurantTable;
use App\Models\StoreSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class KioskController extends Controller
{
    /**
     * Display the customer self-order kiosk screen.
     */
    public function index(): Response
    {
        $categories = Category::query()
            ->orderBy('sort_order')
            ->get();

        $products = Product::query()
            ->where('is_available', true)
            ->with('category:id,name,slug')
            ->orderBy('name')
            ->get();

        $tables = RestaurantTable::query()
            ->orderBy('table_number')
            ->get();

        return Inertia::render('KioskView', [
            'categories' => $categories,
            'products' => $products,
            'tables' => $tables,
            'store_settings' => StoreSetting::getAllAsMap(),
        ]);
    }

    /**
     * Get real-time tables status for Kiosk polling.
     */
    public function getTables(): JsonResponse
    {
        $tables = RestaurantTable::query()
            ->orderBy('table_number')
            ->get();

        return response()->json([
            'success' => true,
            'tables' => $tables,
        ]);
    }

    /**
     * Store a newly created order from the Kiosk.
     */
    public function storeOrder(Request $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'customer_name' => ['required', 'string', 'max:100'],
            'order_type' => ['required', 'in:dine_in,takeaway'],
            'table_number' => ['nullable', 'string', 'max:20', 'required_if:order_type,dine_in'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:99'],
            'items.*.notes' => ['nullable', 'string', 'max:255'],
        ]);

        if ($validated['order_type'] === 'dine_in' && ! empty($validated['table_number'])) {
            $table = RestaurantTable::where('table_number', $validated['table_number'])->first();
            if ($table && $table->status === 'occupied') {
                return response()->json([
                    'success' => false,
                    'message' => 'Meja ' . $validated['table_number'] . ' sedang terisi oleh pelanggan lain. Silakan pilih meja kosong lainnya.',
                ], 422);
            }
        }

        $order = DB::transaction(function () use ($validated): Order {
            // Fetch products to compute secure prices on server
            $productIds = collect($validated['items'])->pluck('product_id')->all();
            $products = Product::whereIn('id', $productIds)->get()->keyBy('id');

            $subtotal = 0;
            $itemsData = [];

            foreach ($validated['items'] as $item) {
                $product = $products->get($item['product_id']);
                if (! $product || ! $product->is_available) {
                    abort(422, 'Salah satu menu yang dipilih sedang tidak tersedia.');
                }

                $quantity = (int) $item['quantity'];
                $unitPrice = (int) $product->price;
                $itemSubtotal = $unitPrice * $quantity;
                $subtotal += $itemSubtotal;

                $itemsData[] = [
                    'product_id' => $product->id,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'subtotal' => $itemSubtotal,
                    'notes' => ! empty($item['notes']) ? trim((string) $item['notes']) : null,
                ];
            }

            $tax = 0; // Tax configuration (set 0 or desired percentage)
            $totalAmount = $subtotal + $tax;

            $order = Order::create([
                'order_number' => Order::generateNextOrderNumber(),
                'customer_name' => trim((string) $validated['customer_name']),
                'table_number' => $validated['order_type'] === 'dine_in' ? trim((string) $validated['table_number']) : null,
                'order_type' => $validated['order_type'],
                'subtotal' => $subtotal,
                'tax' => $tax,
                'total_amount' => $totalAmount,
                'payment_method' => null,
                'amount_paid' => null,
                'change_amount' => null,
                'status' => 'pending_payment',
            ]);

            foreach ($itemsData as $data) {
                $data['order_id'] = $order->id;
                OrderItem::create($data);
            }

            // Update table occupancy status if Dine-In
            if ($order->order_type === 'dine_in' && ! empty($order->table_number)) {
                RestaurantTable::where('table_number', $order->table_number)
                    ->update([
                        'status' => 'occupied',
                        'current_order_id' => $order->id,
                    ]);
            }

            return $order->load(['items.product']);
        });

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Pesanan berhasil dibuat. Silakan menuju kasir untuk pembayaran.',
                'order' => $order,
            ], 201);
        }

        return redirect()->route('kiosk.index')->with('success_order', $order);
    }
}
