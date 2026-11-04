<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\RestaurantTable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PosCashierTest extends TestCase
{
    use RefreshDatabase;

    private Order $order;

    protected function setUp(): void
    {
        parent::setUp();

        $category = Category::create([
            'name' => 'Varian Soto',
            'slug' => 'varian-soto',
            'sort_order' => 1,
        ]);

        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Soto Ayam Ambengan',
            'description' => 'Soto ayam gurih koya',
            'price' => 25000,
            'is_available' => true,
        ]);

        $this->order = Order::create([
            'order_number' => 'SOTO-001',
            'customer_name' => 'Doni',
            'table_number' => '02',
            'order_type' => 'dine_in',
            'subtotal' => 50000,
            'tax' => 0,
            'total_amount' => 50000,
            'status' => 'pending_payment',
        ]);

        OrderItem::create([
            'order_id' => $this->order->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'unit_price' => 25000,
            'subtotal' => 50000,
            'notes' => 'Tanpa seledri',
        ]);
    }

    public function test_pos_screen_is_accessible(): void
    {
        $response = $this->get('/pos');

        $response->assertStatus(200);
    }

    public function test_pos_orders_api_returns_pending_orders_and_stats(): void
    {
        $response = $this->getJson('/pos/api/orders');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'pending_orders',
                'recent_paid_orders',
                'stats' => ['pending_count', 'today_paid_count', 'today_revenue'],
            ])
            ->assertJsonPath('stats.pending_count', 1);
    }

    public function test_cashier_can_process_cash_payment_with_change_calculation(): void
    {
        $response = $this->postJson("/pos/orders/{$this->order->id}/pay", [
            'payment_method' => 'cash',
            'amount_paid' => 100000,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'order' => [
                    'id' => $this->order->id,
                    'payment_method' => 'cash',
                    'amount_paid' => 100000,
                    'change_amount' => 50000,
                    'status' => 'paid',
                ],
            ]);

        $this->assertDatabaseHas('orders', [
            'id' => $this->order->id,
            'status' => 'paid',
            'payment_method' => 'cash',
            'amount_paid' => 100000,
            'change_amount' => 50000,
        ]);
    }

    public function test_cash_payment_fails_if_tender_is_insufficient(): void
    {
        $response = $this->postJson("/pos/orders/{$this->order->id}/pay", [
            'payment_method' => 'cash',
            'amount_paid' => 40000, // Total is 50000
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
            ]);
    }

    public function test_cashier_can_process_qris_payment(): void
    {
        $response = $this->postJson("/pos/orders/{$this->order->id}/pay", [
            'payment_method' => 'qris',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'order' => [
                    'id' => $this->order->id,
                    'payment_method' => 'qris',
                    'amount_paid' => 50000,
                    'change_amount' => 0,
                    'status' => 'paid',
                ],
            ]);

        $this->assertDatabaseHas('orders', [
            'id' => $this->order->id,
            'status' => 'paid',
            'payment_method' => 'qris',
        ]);
    }

    public function test_cashier_can_cancel_pending_order(): void
    {
        $response = $this->postJson("/pos/orders/{$this->order->id}/cancel");

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('orders', [
            'id' => $this->order->id,
            'status' => 'cancelled',
        ]);
    }

    public function test_cashier_can_delete_order(): void
    {
        $response = $this->deleteJson("/pos/orders/{$this->order->id}");

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertDatabaseMissing('orders', [
            'id' => $this->order->id,
        ]);
    }

    public function test_cashier_can_clear_all_history_orders(): void
    {
        $this->order->update(['status' => 'paid']);

        $response = $this->postJson('/pos/orders/clear-all', [
            'scope' => 'history',
        ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertDatabaseMissing('orders', [
            'id' => $this->order->id,
        ]);
    }

    public function test_cashier_can_fetch_revenue_recap(): void
    {
        $response = $this->getJson('/pos/api/revenue-recap?period=day');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'active_period',
                'summary' => [
                    'day' => ['total_revenue', 'total_orders', 'average_order', 'cash_revenue', 'qris_revenue'],
                    'week' => ['total_revenue', 'total_orders', 'average_order', 'cash_revenue', 'qris_revenue'],
                    'month' => ['total_revenue', 'total_orders', 'average_order', 'cash_revenue', 'qris_revenue'],
                ],
                'breakdown',
                'top_products',
            ]);

        $this->getJson('/pos/api/revenue-recap?period=week')->assertStatus(200);
        $this->getJson('/pos/api/revenue-recap?period=month')->assertStatus(200);
    }

    public function test_can_manage_categories_and_products(): void
    {
        // 1. Get menu data
        $this->getJson('/pos/api/menu-data')
            ->assertStatus(200)
            ->assertJsonStructure(['success', 'categories', 'products']);

        // 2. Create Category
        $catResponse = $this->postJson('/pos/categories', [
            'name' => 'Minuman Es',
        ]);
        $catResponse->assertStatus(200)
            ->assertJson(['success' => true]);
        $categoryId = $catResponse->json('category.id');

        $this->assertDatabaseHas('categories', ['name' => 'Minuman Es']);

        // 3. Create Product
        $prodResponse = $this->postJson('/pos/products', [
            'name' => 'Es Jeruk Peras',
            'category_id' => $categoryId,
            'price' => 8000,
            'description' => 'Jeruk asli segar',
            'taste_profile' => 'Manis Asam Segar',
            'ingredients' => ['Jeruk Segar', 'Gula Asli', 'Es Batu'],
            'is_available' => true,
        ]);
        $prodResponse->assertStatus(200)
            ->assertJson(['success' => true]);
        $productId = $prodResponse->json('product.id');

        $this->assertDatabaseHas('products', ['name' => 'Es Jeruk Peras']);

        // 4. Update Product
        $updateResponse = $this->postJson("/pos/products/{$productId}/update", [
            'name' => 'Es Jeruk Peras Manis',
            'category_id' => $categoryId,
            'price' => 9000,
            'description' => 'Jeruk asli manis segar',
            'is_available' => true,
        ]);
        $updateResponse->assertStatus(200);
        $this->assertDatabaseHas('products', ['name' => 'Es Jeruk Peras Manis', 'price' => 9000]);

        // 5. Delete Product
        $this->deleteJson("/pos/products/{$productId}")
            ->assertStatus(200)
            ->assertJson(['success' => true]);
        $this->assertDatabaseMissing('products', ['id' => $productId]);

        // 6. Delete Category
        $this->deleteJson("/pos/categories/{$categoryId}")
            ->assertStatus(200)
            ->assertJson(['success' => true]);
        $this->assertDatabaseMissing('categories', ['id' => $categoryId]);
    }

    public function test_pos_can_manage_tables(): void
    {
        // 1. Fetch tables
        $this->getJson('/pos/api/tables')
            ->assertStatus(200)
            ->assertJsonStructure(['success', 'tables']);

        // 2. Use existing table 07 or first table
        $table = RestaurantTable::firstOrCreate(
            ['table_number' => '07'],
            ['name' => 'Meja 07', 'capacity' => 4, 'status' => 'available']
        );
        $table->update(['status' => 'occupied']);

        // 3. Release table
        $res = $this->postJson("/pos/tables/{$table->id}/release");
        $res->assertStatus(200)->assertJson(['success' => true]);

        $table->refresh();
        $this->assertEquals('available', $table->status);

        // 4. Toggle table
        $this->postJson("/pos/tables/{$table->id}/toggle")
            ->assertStatus(200)->assertJson(['success' => true]);
        $table->refresh();
        $this->assertEquals('occupied', $table->status);
    }
}
