<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\RestaurantTable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KioskOrderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $category = Category::create([
            'name' => 'Varian Soto',
            'slug' => 'varian-soto',
            'sort_order' => 1,
        ]);

        Product::create([
            'category_id' => $category->id,
            'name' => 'Soto Ayam Lamongan',
            'description' => 'Soto koya gurih',
            'price' => 22000,
            'image_url' => 'http://example.com/soto.jpg',
            'is_available' => true,
        ]);
    }

    public function test_kiosk_screen_is_accessible(): void
    {
        $response = $this->get('/kiosk');

        $response->assertStatus(200);
    }

    public function test_customer_can_create_dine_in_order_with_notes(): void
    {
        $product = Product::first();

        $payload = [
            'customer_name' => 'Bambang',
            'order_type' => 'dine_in',
            'table_number' => '05',
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 2,
                    'notes' => 'Koya banyak, tanpa daun bawang',
                ],
            ],
        ];

        $response = $this->postJson('/kiosk/order', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'order' => [
                    'customer_name' => 'Bambang',
                    'order_type' => 'dine_in',
                    'table_number' => '05',
                    'subtotal' => 44000,
                    'total_amount' => 44000,
                    'status' => 'pending_payment',
                ],
            ]);

        $this->assertDatabaseHas('orders', [
            'customer_name' => 'Bambang',
            'table_number' => '05',
            'order_type' => 'dine_in',
            'total_amount' => 44000,
            'status' => 'pending_payment',
        ]);

        $this->assertDatabaseHas('order_items', [
            'product_id' => $product->id,
            'quantity' => 2,
            'unit_price' => 22000,
            'subtotal' => 44000,
            'notes' => 'Koya banyak, tanpa daun bawang',
        ]);
    }

    public function test_customer_can_create_takeaway_order(): void
    {
        $product = Product::first();

        $payload = [
            'customer_name' => 'Dewi',
            'order_type' => 'takeaway',
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 1,
                    'notes' => 'Kuah dipisah',
                ],
            ],
        ];

        $response = $this->postJson('/kiosk/order', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'order' => [
                    'customer_name' => 'Dewi',
                    'order_type' => 'takeaway',
                    'table_number' => null,
                    'total_amount' => 22000,
                    'status' => 'pending_payment',
                ],
            ]);
    }

    public function test_order_requires_table_number_when_dine_in(): void
    {
        $product = Product::first();

        $payload = [
            'customer_name' => 'Joko',
            'order_type' => 'dine_in',
            'table_number' => null,
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 1,
                ],
            ],
        ];

        $response = $this->postJson('/kiosk/order', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['table_number']);
    }

    public function test_kiosk_can_fetch_tables_list(): void
    {
        $response = $this->getJson('/kiosk/api/tables');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'tables' => [
                    '*' => ['id', 'table_number', 'name', 'capacity', 'status'],
                ],
            ]);
    }

    public function test_dine_in_order_marks_table_as_occupied(): void
    {
        $table = RestaurantTable::where('table_number', '01')->first();
        if (! $table) {
            $table = RestaurantTable::create([
                'table_number' => '01',
                'name' => 'Meja 01',
                'capacity' => 4,
                'status' => 'available',
            ]);
        }

        $product = Product::first();

        $payload = [
            'customer_name' => 'Andi',
            'order_type' => 'dine_in',
            'table_number' => '01',
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 1,
                ],
            ],
        ];

        $response = $this->postJson('/kiosk/order', $payload);

        $response->assertStatus(201);

        $table->refresh();
        $this->assertEquals('occupied', $table->status);
        $this->assertNotNull($table->current_order_id);
    }

    public function test_cannot_order_already_occupied_table(): void
    {
        $table = RestaurantTable::where('table_number', '02')->first();
        if (! $table) {
            $table = RestaurantTable::create([
                'table_number' => '02',
                'name' => 'Meja 02',
                'capacity' => 4,
                'status' => 'occupied',
            ]);
        } else {
            $table->update(['status' => 'occupied']);
        }

        $product = Product::first();

        $payload = [
            'customer_name' => 'Budi',
            'order_type' => 'dine_in',
            'table_number' => '02',
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 1,
                ],
            ],
        ];

        $response = $this->postJson('/kiosk/order', $payload);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
            ]);
    }
}
