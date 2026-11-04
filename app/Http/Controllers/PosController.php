<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\RestaurantTable;
use App\Models\StoreSetting;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PosController extends Controller
{
    /**
     * Display the Cashier Tablet POS interface.
     */
    public function index(): Response
    {
        return Inertia::render('CashierPosView', [
            'initialData' => $this->fetchPosData(),
        ]);
    }

    /**
     * Get real-time orders and daily metrics for polling.
     */
    public function getOrders(): JsonResponse
    {
        return response()->json($this->fetchPosData());
    }

    /**
     * Process order payment (Cash or QRIS) and calculate change.
     */
    public function processPayment(Order $order, Request $request): JsonResponse
    {
        if ($order->status !== 'pending_payment') {
            return response()->json([
                'success' => false,
                'message' => 'Pesanan ini sudah diproses sebelumnya.',
            ], 422);
        }

        $validated = $request->validate([
            'payment_method' => ['required', 'in:cash,qris'],
            'amount_paid' => ['required_if:payment_method,cash', 'nullable', 'integer', 'min:0'],
        ]);

        $paymentMethod = $validated['payment_method'];
        $totalAmount = (int) $order->total_amount;

        if ($paymentMethod === 'cash') {
            $amountPaid = (int) $validated['amount_paid'];
            if ($amountPaid < $totalAmount) {
                return response()->json([
                    'success' => false,
                    'message' => 'Jumlah uang yang dibayarkan kurang dari total pesanan.',
                ], 422);
            }
            $changeAmount = $amountPaid - $totalAmount;
        } else {
            // QRIS automatically settles exact amount
            $amountPaid = $totalAmount;
            $changeAmount = 0;
        }

        $order->update([
            'payment_method' => $paymentMethod,
            'amount_paid' => $amountPaid,
            'change_amount' => $changeAmount,
            'status' => 'paid',
            'kitchen_status' => 'queued',
        ]);

        $order->load(['items.product']);

        return response()->json([
            'success' => true,
            'message' => 'Pembayaran berhasil dikonfirmasi.',
            'order' => $order,
        ]);
    }

    /**
     * Cancel an active order.
     */
    public function cancelOrder(Order $order): JsonResponse
    {
        if ($order->status === 'paid') {
            return response()->json([
                'success' => false,
                'message' => 'Pesanan yang sudah dibayar tidak dapat dibatalkan.',
            ], 422);
        }

        $order->update(['status' => 'cancelled']);

        // Release associated table if any
        RestaurantTable::where('current_order_id', $order->id)
            ->update(['status' => 'available', 'current_order_id' => null]);

        return response()->json([
            'success' => true,
            'message' => 'Pesanan berhasil dibatalkan.',
        ]);
    }

    /**
     * Call / recall an order from POS to the customer TV display screen.
     */
    public function callOrder(Order $order): JsonResponse
    {
        $order->update([
            'kitchen_status' => 'ready',
            'ready_at' => $order->ready_at ?? Carbon::now(),
            'call_counter' => $order->call_counter + 1,
        ]);

        return response()->json([
            'success' => true,
            'message' => "Nomor {$order->order_number} dipanggil ke layar TV antrean.",
            'order' => $order->load(['items.product']),
        ]);
    }

    /**
     * Delete an order and its items permanently (e.g. from history or active queue).
     */
    public function destroyOrder(Order $order): JsonResponse
    {
        DB::transaction(function () use ($order): void {
            // Release associated table if any
            RestaurantTable::where('current_order_id', $order->id)
                ->update(['status' => 'available', 'current_order_id' => null]);

            $order->items()->delete();
            $order->delete();
        });

        return response()->json([
            'success' => true,
            'message' => 'Pesanan berhasil dihapus dari sistem.',
        ]);
    }

    /**
     * Delete all orders in history or active queue.
     */
    public function clearAllOrders(Request $request): JsonResponse
    {
        $scope = $request->input('scope', 'history'); // 'history' | 'pending' | 'all'

        DB::transaction(function () use ($scope): void {
            $query = Order::query();

            if ($scope === 'history') {
                $query->where('status', 'paid');
            } elseif ($scope === 'pending') {
                $query->where('status', 'pending_payment');
            }

            $orders = $query->get();
            $orderIds = $orders->pluck('id')->all();

            if (! empty($orderIds)) {
                RestaurantTable::whereIn('current_order_id', $orderIds)
                    ->update(['status' => 'available', 'current_order_id' => null]);
            }

            foreach ($orders as $order) {
                $order->items()->delete();
                $order->delete();
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'Semua pesanan berhasil dihapus.',
        ]);
    }

    /**
     * Get revenue recap for daily, weekly, and monthly periods.
     */
    public function getRevenueRecap(Request $request): JsonResponse
    {
        $period = $request->query('period', 'day');
        if (! in_array($period, ['day', 'week', 'month'], true)) {
            $period = 'day';
        }

        $dayStart = Carbon::today()->startOfDay();
        $dayEnd = Carbon::today()->endOfDay();

        $weekStart = Carbon::now()->startOfWeek(Carbon::MONDAY)->startOfDay();
        $weekEnd = Carbon::now()->endOfWeek(Carbon::SUNDAY)->endOfDay();

        $monthStart = Carbon::now()->startOfMonth()->startOfDay();
        $monthEnd = Carbon::now()->endOfMonth()->endOfDay();

        $summary = [
            'day' => $this->calculatePeriodMetrics($dayStart, $dayEnd),
            'week' => $this->calculatePeriodMetrics($weekStart, $weekEnd),
            'month' => $this->calculatePeriodMetrics($monthStart, $monthEnd),
        ];

        $activeRange = match ($period) {
            'week' => [$weekStart, $weekEnd],
            'month' => [$monthStart, $monthEnd],
            default => [$dayStart, $dayEnd],
        };

        $breakdown = $this->calculatePeriodBreakdown($period, $activeRange[0], $activeRange[1]);
        $topProducts = $this->calculateTopProducts($activeRange[0], $activeRange[1]);

        return response()->json([
            'success' => true,
            'active_period' => $period,
            'summary' => $summary,
            'breakdown' => $breakdown,
            'top_products' => $topProducts,
        ]);
    }

    /**
     * Calculate aggregate metrics for a given date range.
     */
    private function calculatePeriodMetrics(Carbon $start, Carbon $end): array
    {
        $orders = Order::query()
            ->where('status', 'paid')
            ->whereBetween('created_at', [$start, $end])
            ->get();

        $totalRevenue = (int) $orders->sum('total_amount');
        $totalOrders = $orders->count();
        $averageOrder = $totalOrders > 0 ? (int) round($totalRevenue / $totalOrders) : 0;

        $cashOrders = $orders->where('payment_method', 'cash');
        $qrisOrders = $orders->where('payment_method', 'qris');

        $dineInCount = $orders->where('order_type', 'dine_in')->count();
        $takeawayCount = $orders->where('order_type', 'takeaway')->count();

        return [
            'total_revenue' => $totalRevenue,
            'total_orders' => $totalOrders,
            'average_order' => $averageOrder,
            'cash_revenue' => (int) $cashOrders->sum('total_amount'),
            'cash_count' => $cashOrders->count(),
            'qris_revenue' => (int) $qrisOrders->sum('total_amount'),
            'qris_count' => $qrisOrders->count(),
            'dine_in_count' => $dineInCount,
            'takeaway_count' => $takeawayCount,
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
        ];
    }

    /**
     * Calculate detailed breakdown per period (daily transactions, weekly days, monthly days).
     */
    private function calculatePeriodBreakdown(string $period, Carbon $start, Carbon $end): array
    {
        $indonesianDays = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];

        if ($period === 'day') {
            return Order::query()
                ->where('status', 'paid')
                ->whereBetween('created_at', [$start, $end])
                ->orderBy('created_at', 'desc')
                ->get(['id', 'order_number', 'customer_name', 'order_type', 'table_number', 'payment_method', 'total_amount', 'created_at'])
                ->map(fn ($order) => [
                    'id' => $order->id,
                    'order_number' => $order->order_number,
                    'customer_name' => $order->customer_name,
                    'order_type' => $order->order_type,
                    'table_number' => $order->table_number,
                    'payment_method' => $order->payment_method,
                    'total_amount' => (int) $order->total_amount,
                    'time' => $order->created_at->format('H:i'),
                ])
                ->all();
        }

        if ($period === 'week') {
            $days = [];
            $periodDates = CarbonPeriod::create($start->copy()->startOfDay(), $end->copy()->startOfDay());

            foreach ($periodDates as $date) {
                $dayStart = $date->copy()->startOfDay();
                $dayEnd = $date->copy()->endOfDay();

                $dayOrders = Order::query()
                    ->where('status', 'paid')
                    ->whereBetween('created_at', [$dayStart, $dayEnd])
                    ->get(['id', 'total_amount']);

                $days[] = [
                    'date' => $date->toDateString(),
                    'day_name' => $indonesianDays[$date->dayOfWeek],
                    'date_formatted' => $date->format('d M'),
                    'is_today' => $date->isToday(),
                    'order_count' => $dayOrders->count(),
                    'revenue' => (int) $dayOrders->sum('total_amount'),
                ];
            }

            return $days;
        }

        // Period: 'month'
        $daysInMonth = [];
        $periodDates = CarbonPeriod::create($start->copy()->startOfDay(), $end->copy()->startOfDay());

        foreach ($periodDates as $date) {
            $dayStart = $date->copy()->startOfDay();
            $dayEnd = $date->copy()->endOfDay();

            $dayOrders = Order::query()
                ->where('status', 'paid')
                ->whereBetween('created_at', [$dayStart, $dayEnd])
                ->get(['id', 'total_amount']);

            $daysInMonth[] = [
                'date' => $date->toDateString(),
                'day_number' => (int) $date->format('d'),
                'day_name' => $indonesianDays[$date->dayOfWeek],
                'date_formatted' => $date->format('d M'),
                'is_today' => $date->isToday(),
                'is_past_or_today' => $date->isPast() || $date->isToday(),
                'order_count' => $dayOrders->count(),
                'revenue' => (int) $dayOrders->sum('total_amount'),
            ];
        }

        return $daysInMonth;
    }

    /**
     * Calculate top selling products in a date range.
     */
    private function calculateTopProducts(Carbon $start, Carbon $end): array
    {
        $paidOrderIds = Order::query()
            ->where('status', 'paid')
            ->whereBetween('created_at', [$start, $end])
            ->pluck('id');

        if ($paidOrderIds->isEmpty()) {
            return [];
        }

        return DB::table('order_items')
            ->join('products', 'order_items.product_id', '=', 'products.id')
            ->join('categories', 'products.category_id', '=', 'categories.id')
            ->whereIn('order_items.order_id', $paidOrderIds)
            ->select(
                'products.id',
                'products.name',
                'products.image_url',
                'categories.name as category_name',
                DB::raw('SUM(order_items.quantity) as total_qty'),
                DB::raw('SUM(order_items.subtotal) as total_revenue')
            )
            ->groupBy('products.id', 'products.name', 'products.image_url', 'categories.name')
            ->orderByDesc('total_qty')
            ->take(5)
            ->get()
            ->map(fn ($item) => [
                'id' => $item->id,
                'name' => $item->name,
                'category_name' => $item->category_name,
                'image_url' => $item->image_url,
                'total_qty' => (int) $item->total_qty,
                'total_revenue' => (int) $item->total_revenue,
            ])
            ->all();
    }

    /**
     * Toggle item availability from the cashier POS.
     */
    public function toggleProductAvailability(Product $product): JsonResponse
    {
        $product->update([
            'is_available' => ! $product->is_available,
        ]);

        return response()->json([
            'success' => true,
            'is_available' => $product->is_available,
            'product' => $product,
        ]);
    }

    /**
     * Get menu items and categories for management.
     */
    public function getMenuData(): JsonResponse
    {
        $categories = Category::withCount('products')->orderBy('sort_order')->orderBy('name')->get();
        $products = Product::with('category:id,name')->orderBy('created_at', 'desc')->get();

        return response()->json([
            'success' => true,
            'categories' => $categories,
            'products' => $products,
        ]);
    }

    /**
     * Store a new category.
     */
    public function storeCategory(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
        ]);

        $slug = Str::slug($validated['name']);
        $originalSlug = $slug;
        $count = 1;
        while (Category::where('slug', $slug)->exists()) {
            $slug = "{$originalSlug}-{$count}";
            $count++;
        }

        $category = Category::create([
            'name' => trim($validated['name']),
            'slug' => $slug,
            'sort_order' => (int) Category::max('sort_order') + 1,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Kategori berhasil ditambahkan.',
            'category' => $category,
        ]);
    }

    /**
     * Delete a category.
     */
    public function destroyCategory(Category $category): JsonResponse
    {
        if ($category->products()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Kategori tidak dapat dihapus karena masih memiliki produk di dalamnya.',
            ], 422);
        }

        $category->delete();

        return response()->json([
            'success' => true,
            'message' => 'Kategori berhasil dihapus.',
        ]);
    }

    /**
     * Store a newly created product.
     */
    public function storeProduct(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'category_id' => ['required', 'exists:categories,id'],
            'price' => ['required', 'integer', 'min:0'],
            'description' => ['nullable', 'string', 'max:500'],
            'taste_profile' => ['nullable', 'string', 'max:255'],
            'ingredients' => ['nullable'],
            'image_url' => ['nullable', 'string', 'max:1000'],
            'image_file' => ['nullable', 'image', 'max:4096'],
            'is_available' => ['nullable'],
        ]);

        $imageUrl = $validated['image_url'] ?? null;

        if ($request->hasFile('image_file')) {
            $file = $request->file('image_file');
            $filename = time() . '_' . Str::random(8) . '.' . $file->getClientOriginalExtension();
            $destinationPath = public_path('uploads/menus');
            if (! file_exists($destinationPath)) {
                mkdir($destinationPath, 0755, true);
            }
            $file->move($destinationPath, $filename);
            $imageUrl = '/uploads/menus/' . $filename;
        }

        $ingredients = [];
        if (! empty($validated['ingredients'])) {
            if (is_array($validated['ingredients'])) {
                $ingredients = array_values(array_filter(array_map('trim', $validated['ingredients'])));
            } else {
                $ingredients = array_values(array_filter(array_map('trim', explode(',', (string) $validated['ingredients']))));
            }
        }

        $product = Product::create([
            'name' => trim($validated['name']),
            'category_id' => $validated['category_id'],
            'price' => (int) $validated['price'],
            'description' => ! empty($validated['description']) ? trim($validated['description']) : null,
            'taste_profile' => ! empty($validated['taste_profile']) ? trim($validated['taste_profile']) : null,
            'ingredients' => $ingredients,
            'image_url' => $imageUrl,
            'is_available' => filter_var($request->input('is_available', true), FILTER_VALIDATE_BOOLEAN),
        ]);

        $product->load('category:id,name');

        return response()->json([
            'success' => true,
            'message' => 'Menu berhasil ditambahkan.',
            'product' => $product,
        ]);
    }

    /**
     * Update an existing product.
     */
    public function updateProduct(Product $product, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'category_id' => ['required', 'exists:categories,id'],
            'price' => ['required', 'integer', 'min:0'],
            'description' => ['nullable', 'string', 'max:500'],
            'taste_profile' => ['nullable', 'string', 'max:255'],
            'ingredients' => ['nullable'],
            'image_url' => ['nullable', 'string', 'max:1000'],
            'image_file' => ['nullable', 'image', 'max:4096'],
            'is_available' => ['nullable'],
        ]);

        $imageUrl = $product->image_url;
        if ($request->has('image_url') && $request->input('image_url') !== null) {
            $imageUrl = $request->input('image_url');
        }

        if ($request->hasFile('image_file')) {
            $file = $request->file('image_file');
            $filename = time() . '_' . Str::random(8) . '.' . $file->getClientOriginalExtension();
            $destinationPath = public_path('uploads/menus');
            if (! file_exists($destinationPath)) {
                mkdir($destinationPath, 0755, true);
            }
            $file->move($destinationPath, $filename);
            $imageUrl = '/uploads/menus/' . $filename;
        }

        $ingredients = $product->ingredients ?? [];
        if ($request->has('ingredients')) {
            $raw = $request->input('ingredients');
            if (is_array($raw)) {
                $ingredients = array_values(array_filter(array_map('trim', $raw)));
            } elseif (is_string($raw)) {
                $ingredients = array_values(array_filter(array_map('trim', explode(',', $raw))));
            }
        }

        $product->update([
            'name' => trim($validated['name']),
            'category_id' => $validated['category_id'],
            'price' => (int) $validated['price'],
            'description' => ! empty($validated['description']) ? trim($validated['description']) : null,
            'taste_profile' => ! empty($validated['taste_profile']) ? trim($validated['taste_profile']) : null,
            'ingredients' => $ingredients,
            'image_url' => $imageUrl,
            'is_available' => filter_var($request->input('is_available', $product->is_available), FILTER_VALIDATE_BOOLEAN),
        ]);

        $product->load('category:id,name');

        return response()->json([
            'success' => true,
            'message' => 'Menu berhasil diperbarui.',
            'product' => $product,
        ]);
    }

    /**
     * Delete a product.
     */
    public function destroyProduct(Product $product): JsonResponse
    {
        $product->delete();

        return response()->json([
            'success' => true,
            'message' => 'Menu berhasil dihapus.',
        ]);
    }

    /**
     * Fetch all active orders, recent history, and daily stats.
     *
     * @return array{
     *     pending_orders: \Illuminate\Database\Eloquent\Collection,
     *     recent_paid_orders: \Illuminate\Database\Eloquent\Collection,
     *     stats: array{pending_count: int, today_paid_count: int, today_revenue: int}
     * }
     */
    private function fetchPosData(): array
    {
        $today = Carbon::today();

        $pendingOrders = Order::query()
            ->where('status', 'pending_payment')
            ->with(['items.product'])
            ->orderBy('created_at', 'asc')
            ->get();

        $recentPaidOrders = Order::query()
            ->where('status', 'paid')
            ->whereDate('created_at', $today)
            ->with(['items.product'])
            ->orderBy('updated_at', 'desc')
            ->take(15)
            ->get();

        $todayPaidCount = Order::query()
            ->where('status', 'paid')
            ->whereDate('created_at', $today)
            ->count();

        $todayRevenue = (int) Order::query()
            ->where('status', 'paid')
            ->whereDate('created_at', $today)
            ->sum('total_amount');

        $tables = RestaurantTable::query()
            ->with(['currentOrder:id,order_number,customer_name,status,created_at'])
            ->orderBy('table_number')
            ->get();

        return [
            'pending_orders' => $pendingOrders,
            'recent_paid_orders' => $recentPaidOrders,
            'tables' => $tables,
            'store_settings' => StoreSetting::getAllAsMap(),
            'stats' => [
                'pending_count' => $pendingOrders->count(),
                'today_paid_count' => $todayPaidCount,
                'today_revenue' => $todayRevenue,
            ],
        ];
    }

    /**
     * Get real-time restaurant tables.
     */
    public function getTables(): JsonResponse
    {
        $tables = RestaurantTable::query()
            ->with(['currentOrder:id,order_number,customer_name,status,created_at'])
            ->orderBy('table_number')
            ->get();

        return response()->json([
            'success' => true,
            'tables' => $tables,
        ]);
    }

    /**
     * Release / clear a table so it becomes available again.
     */
    public function releaseTable(RestaurantTable $table): JsonResponse
    {
        $table->update([
            'status' => 'available',
            'current_order_id' => null,
        ]);

        return response()->json([
            'success' => true,
            'message' => "Meja {$table->table_number} sekarang kosong & siap ditempati.",
            'table' => $table,
        ]);
    }

    /**
     * Toggle table occupancy status manually from POS.
     */
    public function toggleTableStatus(RestaurantTable $table): JsonResponse
    {
        $newStatus = $table->status === 'available' ? 'occupied' : 'available';

        $table->update([
            'status' => $newStatus,
            'current_order_id' => $newStatus === 'available' ? null : $table->current_order_id,
        ]);

        return response()->json([
            'success' => true,
            'message' => "Status Meja {$table->table_number} diubah menjadi {$newStatus}.",
            'table' => $table,
        ]);
    }

    /**
     * Get store profile & receipt settings.
     */
    public function getStoreSettings(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'settings' => StoreSetting::getAllAsMap(),
        ]);
    }

    /**
     * Update store profile & receipt settings.
     */
    public function updateStoreSettings(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'store_name' => ['required', 'string', 'max:100'],
            'store_tagline' => ['nullable', 'string', 'max:255'],
            'store_branch' => ['nullable', 'string', 'max:100'],
            'store_address' => ['nullable', 'string', 'max:255'],
            'store_phone' => ['nullable', 'string', 'max:50'],
            'store_instagram' => ['nullable', 'string', 'max:100'],
            'wifi_ssid' => ['nullable', 'string', 'max:100'],
            'wifi_password' => ['nullable', 'string', 'max:100'],
            'receipt_footer' => ['nullable', 'string', 'max:500'],
            'tax_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        StoreSetting::setMany($validated);

        return response()->json([
            'success' => true,
            'message' => 'Pengaturan profil toko & format struk berhasil disimpan.',
            'settings' => StoreSetting::getAllAsMap(),
        ]);
    }

    /**
     * Get data for End of Day / Shift Z-Report thermal print.
     */
    public function getZReportData(): JsonResponse
    {
        $today = Carbon::today();
        $paidOrders = Order::query()
            ->whereDate('created_at', $today)
            ->where('status', 'paid')
            ->with(['items.product'])
            ->get();

        $totalRevenue = (int) $paidOrders->sum('total_amount');
        $cashOrders = $paidOrders->where('payment_method', 'cash');
        $qrisOrders = $paidOrders->where('payment_method', 'qris');

        $totalCash = (int) $cashOrders->sum('total_amount');
        $totalQris = (int) $qrisOrders->sum('total_amount');
        $totalCashReceived = (int) $cashOrders->sum('amount_paid');
        $totalCashChange = (int) $cashOrders->sum('change_amount');

        // Product sales aggregation
        $productSales = [];
        foreach ($paidOrders as $order) {
            foreach ($order->items as $item) {
                $pName = $item->product->name ?? 'Menu';
                if (! isset($productSales[$pName])) {
                    $productSales[$pName] = [
                        'name' => $pName,
                        'quantity' => 0,
                        'total' => 0,
                    ];
                }
                $productSales[$pName]['quantity'] += (int) $item->quantity;
                $productSales[$pName]['total'] += (int) $item->subtotal;
            }
        }

        // Sort descending by quantity
        usort($productSales, fn ($a, $b) => $b['quantity'] <=> $a['quantity']);

        // Table status summary
        $tables = RestaurantTable::all();
        $tablesAvailable = $tables->where('status', 'available')->count();
        $tablesOccupied = $tables->where('status', 'occupied')->count();

        return response()->json([
            'success' => true,
            'report' => [
                'date_formatted' => Carbon::now()->format('d/m/Y H:i:s'),
                'date' => Carbon::now()->format('d/m/Y'),
                'time' => Carbon::now()->format('H:i:s'),
                'terminal' => 'POS Terminal 01',
                'cashier' => 'Staff Kasir Parsoey',
                'total_orders' => $paidOrders->count(),
                'total_revenue' => $totalRevenue,
                'cash' => [
                    'count' => $cashOrders->count(),
                    'revenue' => $totalCash,
                    'amount_received' => $totalCashReceived,
                    'change_given' => $totalCashChange,
                    'cash_in_drawer' => $totalCash,
                ],
                'qris' => [
                    'count' => $qrisOrders->count(),
                    'revenue' => $totalQris,
                ],
                'product_sales' => array_values($productSales),
                'total_portions_sold' => array_sum(array_column($productSales, 'quantity')),
                'tables' => [
                    'total' => $tables->count(),
                    'available' => $tablesAvailable,
                    'occupied' => $tablesOccupied,
                ],
                'store_settings' => StoreSetting::getAllAsMap(),
            ],
        ]);
    }

    /**
     * Export sales report to Excel CSV.
     */
    public function exportCsv(Request $request): StreamedResponse
    {
        $period = $request->input('period', 'today'); // 'today' | 'week' | 'month' | 'all'
        $query = Order::query()->where('status', 'paid')->with(['items.product'])->orderBy('created_at', 'desc');

        $today = Carbon::today();
        if ($period === 'today') {
            $query->whereDate('created_at', $today);
        } elseif ($period === 'week') {
            $query->where('created_at', '>=', Carbon::now()->startOfWeek());
        } elseif ($period === 'month') {
            $query->where('created_at', '>=', Carbon::now()->startOfMonth());
        }

        $orders = $query->get();

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="Laporan-Penjualan-Soto-Parsoey-' . $period . '-' . date('Y-m-d') . '.csv"',
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        $callback = function () use ($orders): void {
            $file = fopen('php://output', 'w');
            // Write UTF-8 BOM so Excel opens with Indonesian accents properly
            fputs($file, "\xEF\xBB\xBF");

            // Header row
            fputcsv($file, [
                'No. Order',
                'Waktu Transaksi',
                'Nama Pelanggan',
                'Tipe Layanan',
                'No. Meja',
                'Rincian Menu',
                'Metode Pembayaran',
                'Uang Diterima (Rp)',
                'Kembalian (Rp)',
                'Subtotal (Rp)',
                'Pajak (Rp)',
                'Total Tagihan (Rp)',
                'Status',
            ]);

            foreach ($orders as $order) {
                $itemsStr = $order->items->map(function ($item) {
                    $note = ! empty($item->notes) ? " (Note: {$item->notes})" : '';
                    return "{$item->quantity}x " . ($item->product->name ?? 'Menu') . $note;
                })->implode('; ');

                fputcsv($file, [
                    $order->order_number,
                    $order->created_at->format('d/m/Y H:i:s'),
                    $order->customer_name,
                    $order->order_type === 'dine_in' ? 'Makan di Tempat' : 'Bawa Pulang (Takeaway)',
                    $order->table_number ?? '-',
                    $itemsStr,
                    strtoupper((string) $order->payment_method),
                    $order->amount_paid ?? $order->total_amount,
                    $order->change_amount ?? 0,
                    $order->subtotal,
                    $order->tax,
                    $order->total_amount,
                    'LUNAS',
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
