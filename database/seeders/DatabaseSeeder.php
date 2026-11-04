<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Categories
        $categoriesData = [
            [
                'name' => 'Varian Soto',
                'slug' => 'varian-soto',
                'sort_order' => 1,
            ],
            [
                'name' => 'Lauk Pendamping',
                'slug' => 'lauk-pendamping',
                'sort_order' => 2,
            ],
            [
                'name' => 'Minuman',
                'slug' => 'minuman',
                'sort_order' => 3,
            ],
        ];

        $categories = [];
        foreach ($categoriesData as $cat) {
            $categories[$cat['slug']] = Category::create($cat);
        }

        // 2. Products
        $productsData = [
            // Soto items
            [
                'category' => 'varian-soto',
                'name' => 'Soto Ayam Lamongan',
                'description' => 'Suwiran ayam kampung empuk, kuah kuning kental harum rempah, bihun, kol, dan koya gurih melimpah.',
                'price' => 22000,
                'image_url' => '/images/menu/soto-ayam-lamongan.svg',
                'is_available' => true,
            ],
            [
                'category' => 'varian-soto',
                'name' => 'Soto Ayam Ambengan',
                'description' => 'Soto khas Surabaya bertabur bubuk koya gurih lezat, suwiran ayam, irisan telur rebus, dan seledri.',
                'price' => 25000,
                'image_url' => '/images/menu/soto-ayam-ambengan.svg',
                'is_available' => true,
            ],
            [
                'category' => 'varian-soto',
                'name' => 'Soto Daging Sapi Madura',
                'description' => 'Irisan daging sapi empuk, tauge segar, daun bawang, dan kuah kaldu rempah bening gurih mantap.',
                'price' => 32000,
                'image_url' => '/images/menu/soto-daging-sapi.svg',
                'is_available' => true,
            ],
            [
                'category' => 'varian-soto',
                'name' => 'Soto Betawi Daging & Emping',
                'description' => 'Daging sapi pilihan berkuah santan campur susu gurih nan creamy, disajikan dengan taburan emping melinjo renyah.',
                'price' => 35000,
                'image_url' => '/images/menu/soto-betawi.svg',
                'is_available' => true,
            ],
            [
                'category' => 'varian-soto',
                'name' => 'Soto Mie Bogor',
                'description' => 'Potongan daging sengkel, risol bihun garing renyah, mie kuning, kol, tomat dan kuah kaldu sapi merah gurih.',
                'price' => 28000,
                'image_url' => '/images/menu/soto-mie-bogor.svg',
                'is_available' => true,
            ],
            [
                'category' => 'varian-soto',
                'name' => 'Soto Kudus Mangkok Tradisional',
                'description' => 'Soto khas Kudus dengan aroma bawang putih goreng dan jintan harum khas mangkuk tanah liat.',
                'price' => 26000,
                'image_url' => '/images/menu/soto-kudus.svg',
                'is_available' => true,
            ],

            // Lauk Pendamping
            [
                'category' => 'lauk-pendamping',
                'name' => 'Sate Telur Puyuh Bacem',
                'description' => 'Telur puyuh masak bacem kecap rempah manis gurih meresap, isi 4 butir per tusuk.',
                'price' => 5000,
                'image_url' => '/images/menu/sate-telur-puyuh.svg',
                'is_available' => true,
            ],
            [
                'category' => 'lauk-pendamping',
                'name' => 'Sate Usus Ayam Bacem',
                'description' => 'Usus ayam bersih olahan bumbu bacem gurih legit dipanggang wangi khas angkringan.',
                'price' => 4000,
                'image_url' => '/images/menu/sate-usus-ayam.svg',
                'is_available' => true,
            ],
            [
                'category' => 'lauk-pendamping',
                'name' => 'Sate Ati Ampela',
                'description' => 'Ati dan rempela ayam ungkep rempah kuning lembut dan gurih.',
                'price' => 5000,
                'image_url' => '/images/menu/sate-ati-ampela.svg',
                'is_available' => true,
            ],
            [
                'category' => 'lauk-pendamping',
                'name' => 'Perkedel Kentang Asli',
                'description' => 'Perkedel kentang lembut dengan bumbu rempah dan balutan telur gurih keemasan.',
                'price' => 4000,
                'image_url' => '/images/menu/perkedel-kentang.svg',
                'is_available' => true,
            ],
            [
                'category' => 'lauk-pendamping',
                'name' => 'Kerupuk Kaleng Putih (Blekk)',
                'description' => 'Kerupuk mawar putih renyah garing, teman setia menyantap kuah soto hangat.',
                'price' => 2000,
                'image_url' => '/images/menu/kerupuk-kaleng.svg',
                'is_available' => true,
            ],
            [
                'category' => 'lauk-pendamping',
                'name' => 'Emping Melinjo Renyah',
                'description' => 'Emping melinjo goreng garing dengan cita rasa khas sedikit pahit gurih asin.',
                'price' => 6000,
                'image_url' => '/images/menu/emping-melinjo.svg',
                'is_available' => true,
            ],
            [
                'category' => 'lauk-pendamping',
                'name' => 'Nasi Putih Pulen Wangi',
                'description' => 'Nasi putih pandan wangi hangat bertabur bawang goreng merah harum renyah.',
                'price' => 6000,
                'image_url' => '/images/menu/nasi-putih.svg',
                'is_available' => true,
            ],
            [
                'category' => 'lauk-pendamping',
                'name' => 'Telur Bebek Asin Brebes',
                'description' => 'Telur asin khas Brebes dengan bagian kuning telur berminyak dan masir mantap.',
                'price' => 6000,
                'image_url' => '/images/menu/telur-asin.svg',
                'is_available' => true,
            ],

            // Minuman
            [
                'category' => 'minuman',
                'name' => 'Es Teh Manis Melati',
                'description' => 'Seduhan teh melati wangi khas Solo disajikan manis dingin menyegarkan.',
                'price' => 5000,
                'image_url' => '/images/menu/es-teh-manis.svg',
                'is_available' => true,
            ],
            [
                'category' => 'minuman',
                'name' => 'Es Teh Tawar Segar',
                'description' => 'Teh wangi melati dingin tanpa gula, pelepas dahaga yang ringan dan nikmat.',
                'price' => 3000,
                'image_url' => '/images/menu/es-teh-tawar.svg',
                'is_available' => true,
            ],
            [
                'category' => 'minuman',
                'name' => 'Es Jeruk Peras Murni',
                'description' => 'Jeruk nipis & manis peras segar asli tanpa sirup buatan, kaya vitamin C alami.',
                'price' => 8000,
                'image_url' => '/images/menu/es-jeruk.svg',
                'is_available' => true,
            ],
            [
                'category' => 'minuman',
                'name' => 'Jeruk Hangat Peras',
                'description' => 'Air perasan jeruk asli hangat menenangkan dan pas mendampingi santapan berkuah.',
                'price' => 8000,
                'image_url' => '/images/menu/jeruk-hangat.svg',
                'is_available' => true,
            ],
            [
                'category' => 'minuman',
                'name' => 'Es Cendol Dawet Gula Aren',
                'description' => 'Cendol kenyal pandan berpadu santan gurih dan sirup gula aren asli wangi nangka.',
                'price' => 12000,
                'image_url' => '/images/menu/es-cendol.svg',
                'is_available' => true,
            ],
            [
                'category' => 'minuman',
                'name' => 'Air Mineral Botol 600ml',
                'description' => 'Air mineral higienis dingin atau suhu ruang.',
                'price' => 4000,
                'image_url' => '/images/menu/air-mineral.svg',
                'is_available' => true,
            ],
        ];
            ],
            [
                'category' => 'minuman',
                'name' => 'Air Mineral Botol 600ml',
                'description' => 'Air mineral higienis dingin atau suhu ruang.',
                'price' => 4000,
                'image_url' => 'https://images.unsplash.com/photo-1548839140-29a749e1bc4e?auto=format&fit=crop&w=600&q=80',
                'is_available' => true,
            ],
        ];

        $createdProducts = [];
        foreach ($productsData as $item) {
            $catSlug = $item['category'];
            unset($item['category']);
            $item['category_id'] = $categories[$catSlug]->id;
            $createdProducts[$item['name']] = Product::create($item);
        }

        // 3. Initial Sample Orders (demonstrating Kiosk to POS queue)
        $order1 = Order::create([
            'order_number' => 'SOTO-001',
            'customer_name' => 'Budi Santoso',
            'table_number' => '04',
            'order_type' => 'dine_in',
            'subtotal' => 67000,
            'tax' => 0,
            'total_amount' => 67000,
            'payment_method' => null,
            'amount_paid' => null,
            'change_amount' => null,
            'status' => 'pending_payment',
        ]);

        OrderItem::create([
            'order_id' => $order1->id,
            'product_id' => $createdProducts['Soto Ayam Ambengan']->id,
            'quantity' => 2,
            'unit_price' => 25000,
            'subtotal' => 50000,
            'notes' => 'Koya banyak, kuah panas',
        ]);

        OrderItem::create([
            'order_id' => $order1->id,
            'product_id' => $createdProducts['Sate Telur Puyuh Bacem']->id,
            'quantity' => 2,
            'unit_price' => 5000,
            'subtotal' => 10000,
            'notes' => null,
        ]);

        OrderItem::create([
            'order_id' => $order1->id,
            'product_id' => $createdProducts['Es Teh Manis Melati']->id,
            'quantity' => 1,
            'unit_price' => 5000,
            'subtotal' => 5000,
            'notes' => 'Sedikit es batu',
        ]);

        OrderItem::create([
            'order_id' => $order1->id,
            'product_id' => $createdProducts['Kerupuk Kaleng Putih (Blekk)']->id,
            'quantity' => 1,
            'unit_price' => 2000,
            'subtotal' => 2000,
            'notes' => null,
        ]);

        $order2 = Order::create([
            'order_number' => 'SOTO-002',
            'customer_name' => 'Siti Rahmawati',
            'table_number' => null,
            'order_type' => 'takeaway',
            'subtotal' => 43000,
            'tax' => 0,
            'total_amount' => 43000,
            'payment_method' => null,
            'amount_paid' => null,
            'change_amount' => null,
            'status' => 'pending_payment',
        ]);

        OrderItem::create([
            'order_id' => $order2->id,
            'product_id' => $createdProducts['Soto Betawi Daging & Emping']->id,
            'quantity' => 1,
            'unit_price' => 35000,
            'subtotal' => 35000,
            'notes' => 'Kuah dipisah, sambal banyak',
        ]);

        OrderItem::create([
            'order_id' => $order2->id,
            'product_id' => $createdProducts['Es Jeruk Peras Murni']->id,
            'quantity' => 1,
            'unit_price' => 8000,
            'subtotal' => 8000,
            'notes' => 'Gula dipisah',
        ]);
    }
}
