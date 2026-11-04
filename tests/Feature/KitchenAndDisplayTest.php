<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KitchenAndDisplayTest extends TestCase
{
    use RefreshDatabase;

    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $category = Category::create([
            'name' => 'Varian Soto',
            'slug' => 'varian-soto',
            'sort_order' => 1,
        ]);

        $this->product = Product::create([
            'category_id' => $category->id,
            'name' => 'Soto Betawi Daging',
            'description' => 'Soto kuah susu rempah',
            'price' => 35000,
            'image_url' => 'http://example.com/soto.jpg',
            'is_available' => true,
        ]);
    }

    public function test_kitchen_screen_is_accessible(): void
    {
        $response = $this->get('/kitchen');
        $response->assertStatus(200);
    }

    public function test_display_screen_is_accessible(): void
    {
        $response = $this->get('/display');
        $response->assertStatus(200);
    }

    public function test_kitchen_order_lifecycle(): void
    {
        // 1. Create a paid order
        $order = Order::create([
            'order_number' => 'SOTO-001',
            'customer_name' => 'Budi Santoso',
            'table_number' => '03',
            'order_type' => 'dine_in',
            'subtotal' => 35000,
            'tax' => 0,
            'total_amount' => 35000,
            'payment_method' => 'cash',
            'amount_paid' => 50000,
            'change_amount' => 15000,
            'status' => 'paid',
            'kitchen_status' => 'queued',
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $this->product->id,
            'quantity' => 1,
            'unit_price' => 35000,
            'subtotal' => 35000,
            'notes' => 'Kuah dipisah, koya banyak',
        ]);

        // 2. Fetch orders from kitchen API
        $res = $this->getJson('/kitchen/api/orders');
        $res->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'orders',
                    'counts' => ['queued', 'cooking', 'ready', 'completed_today'],
                ],
            ]);

        // 3. Start cooking
        $cookRes = $this->postJson("/kitchen/orders/{$order->id}/start");
        $cookRes->assertStatus(200)->assertJson(['success' => true]);
        $order->refresh();
        $this->assertEquals('cooking', $order->kitchen_status);
        $this->assertNotNull($order->cooking_started_at);

        // 4. Check display queue API -> order should appear under 'preparing'
        $dispRes = $this->getJson('/display/api/queue');
        $dispRes->assertStatus(200);
        $preparing = collect($dispRes->json('data.preparing'))->pluck('order_number')->all();
        $this->assertContains('SOTO-001', $preparing);

        // 5. Mark ready for pickup
        $readyRes = $this->postJson("/kitchen/orders/{$order->id}/ready");
        $readyRes->assertStatus(200)->assertJson(['success' => true]);
        $order->refresh();
        $this->assertEquals('ready', $order->kitchen_status);
        $this->assertNotNull($order->ready_at);
        $this->assertEquals(1, $order->call_counter);

        // 6. Check display queue API -> order should appear under 'ready' and 'latest_call'
        $dispRes2 = $this->getJson('/display/api/queue');
        $ready = collect($dispRes2->json('data.ready'))->pluck('order_number')->all();
        $this->assertContains('SOTO-001', $ready);
        $this->assertEquals('SOTO-001', $dispRes2->json('data.latest_call.order_number'));

        // 7. Recall order
        $recallRes = $this->postJson("/kitchen/orders/{$order->id}/recall");
        $recallRes->assertStatus(200);
        $order->refresh();
        $this->assertEquals(2, $order->call_counter);

        // 8. Mark completed
        $compRes = $this->postJson("/kitchen/orders/{$order->id}/complete");
        $compRes->assertStatus(200);
        $order->refresh();
        $this->assertEquals('completed', $order->kitchen_status);
        $this->assertNotNull($order->completed_at);
    }

    public function test_pos_can_call_order_to_tv(): void
    {
        $order = Order::create([
            'order_number' => 'SOTO-002',
            'customer_name' => 'Siti',
            'table_number' => '05',
            'order_type' => 'dine_in',
            'subtotal' => 35000,
            'tax' => 0,
            'total_amount' => 35000,
            'payment_method' => 'cash',
            'status' => 'paid',
            'kitchen_status' => 'queued',
        ]);

        $res = $this->postJson("/pos/orders/{$order->id}/call");
        $res->assertStatus(200)->assertJson(['success' => true]);

        $order->refresh();
        $this->assertEquals('ready', $order->kitchen_status);
        $this->assertEquals(1, $order->call_counter);
    }
}
