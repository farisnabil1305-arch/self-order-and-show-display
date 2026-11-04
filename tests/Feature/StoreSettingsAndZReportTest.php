<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\RestaurantTable;
use App\Models\StoreSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StoreSettingsAndZReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_fetch_and_update_store_settings(): void
    {
        $getResponse = $this->getJson('/pos/api/store-settings');
        $getResponse->assertOk();
        $this->assertEquals('Soto Betawi Parsoey', $getResponse->json('settings.store_name'));

        $updateResponse = $this->postJson('/pos/api/store-settings', [
            'store_name' => 'SOTO PARSOEY GRAND GALAXY',
            'store_branch' => 'Cabang Galaxy Bekasi',
            'store_phone' => '08123456789',
            'wifi_ssid' => 'PARSOEY-5G',
            'wifi_password' => 'kuahsantan123',
            'receipt_footer' => "Terima kasih atas kunjungan Anda!\nKritik & saran ke IG @sotoparsoey",
            'tax_percentage' => 0,
        ]);

        $updateResponse->assertOk();
        $this->assertTrue($updateResponse->json('success'));
        $this->assertEquals('SOTO PARSOEY GRAND GALAXY', StoreSetting::get('store_name'));
        $this->assertEquals('Cabang Galaxy Bekasi', StoreSetting::get('store_branch'));
        $this->assertEquals('PARSOEY-5G', StoreSetting::get('wifi_ssid'));
        $this->assertEquals('kuahsantan123', StoreSetting::get('wifi_password'));
    }

    public function test_can_generate_z_report_data(): void
    {
        $table = RestaurantTable::firstOrCreate(
            ['table_number' => '15'],
            ['name' => 'Meja 15', 'capacity' => 4, 'status' => 'available']
        );

        $category = \App\Models\Category::firstOrCreate(
            ['slug' => 'soto'],
            ['name' => 'Soto', 'sort_order' => 1]
        );

        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Soto Kudus',
            'slug' => 'soto-kudus',
            'price' => 25000,
            'is_available' => true,
        ]);

        $order = Order::create([
            'order_number' => 'SOTO-TEST-999',
            'order_type' => 'dine_in',
            'customer_name' => 'Pelanggan Z-Report',
            'table_number' => '15',
            'status' => 'paid',
            'subtotal' => 25000,
            'tax' => 0,
            'total_amount' => 25000,
            'payment_method' => 'cash',
            'amount_paid' => 50000,
            'change_amount' => 25000,
            'paid_at' => now(),
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'unit_price' => 25000,
            'quantity' => 1,
            'subtotal' => 25000,
        ]);

        $response = $this->getJson('/pos/api/z-report');
        $response->assertOk();
        $response->assertJsonStructure([
            'success',
            'report' => [
                'date_formatted',
                'terminal',
                'cashier',
                'total_orders',
                'total_revenue',
                'cash',
                'qris',
                'product_sales',
                'tables',
                'store_settings',
            ],
        ]);

        $this->assertEquals(1, $response->json('report.total_orders'));
        $this->assertEquals(25000, $response->json('report.total_revenue'));
        $this->assertEquals(25000, $response->json('report.cash.revenue'));
    }

    public function test_can_export_csv_report(): void
    {
        $response = $this->get('/pos/reports/export-csv?period=today');
        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }

    public function test_can_clear_all_orders(): void
    {
        $order = Order::create([
            'order_number' => 'SOTO-DEL-01',
            'order_type' => 'takeaway',
            'customer_name' => 'Budi Hapus',
            'status' => 'paid',
            'subtotal' => 10000,
            'tax' => 0,
            'total_amount' => 10000,
            'payment_method' => 'cash',
            'paid_amount' => 10000,
            'change_amount' => 0,
            'paid_at' => now(),
        ]);

        $response = $this->postJson('/pos/orders/clear-all', [
            'scope' => 'history',
        ]);

        $response->assertOk();
        $this->assertTrue($response->json('success'));
        $this->assertDatabaseMissing('orders', ['id' => $order->id]);
    }
}
