<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;

class MenuDataSeeder extends Seeder
{
    public function run(): void
    {
        // Pastikan Kategori ada dan terstruktur
        $catMinuman = Category::firstOrCreate(
            ['slug' => 'minuman'],
            ['name' => 'Minuman Segar', 'sort_order' => 3]
        );
        $catMakanan = Category::firstOrCreate(
            ['slug' => 'makanan'],
            ['name' => 'Soto & Makanan Utama', 'sort_order' => 1]
        );
        $catToping = Category::firstOrCreate(
            ['slug' => 'toping-tambahan'],
            ['name' => 'Lauk & Toping Tambahan', 'sort_order' => 2]
        );

        // Update display names if needed
        $catMakanan->update(['name' => 'Soto & Makanan Utama']);
        $catToping->update(['name' => 'Lauk & Toping Tambahan']);
        $catMinuman->update(['name' => 'Minuman Segar']);

        $menus = [
            // ================= 1. SOTO & MAKANAN UTAMA =================
            [
                'category_id' => $catMakanan->id,
                'name' => 'Soto Betawi Daging Sapi Spesial',
                'description' => 'Daging sapi has dalam empuk dengan kuah santan campur susu gurih nan creamy khas Parsoey, tomat segar, kentang, dan taburan emping melinjo renyah.',
                'taste_profile' => 'Gurih, creamy susu rempah, lembut daging sapi empuk',
                'ingredients' => ['Daging sapi has dalam', 'Kuah santan susu rempah', 'Emping melinjo', 'Tomat segar', 'Kentang goreng', 'Bawang goreng'],
                'price' => 35000,
                'image_url' => '/images/menu/soto-betawi.svg',
                'is_available' => true,
            ],
            [
                'category_id' => $catMakanan->id,
                'name' => 'Soto Betawi Campur (Daging & Babat)',
                'description' => 'Kombinasi daging sapi empuk dan babat segar berkuah santan susu rempah gurih nan kental, disajikan hangat dengan taburan emping dan daun bawang.',
                'taste_profile' => 'Gurih pekat, kenyal lembut, harum rempah pala & kapulaga',
                'ingredients' => ['Daging sapi', 'Babat sapi bersih', 'Kuah santan susu rempah', 'Emping melinjo', 'Tomat', 'Daun bawang'],
                'price' => 38000,
                'image_url' => '/images/menu/soto-betawi.svg',
                'is_available' => true,
            ],
            [
                'category_id' => $catMakanan->id,
                'name' => 'Soto Betawi Ayam Suwir',
                'description' => 'Suwiran ayam kampung gurih empuk berpadu kuah santan susu rempah lembut khas Betawi dengan kentang goreng dan emping renyah.',
                'taste_profile' => 'Gurih lembut, creamy, rempah ringan menyegarkan',
                'ingredients' => ['Ayam kampung suwir', 'Kuah santan susu rempah', 'Kentang goreng', 'Tomat segar', 'Emping melinjo'],
                'price' => 26000,
                'image_url' => '/images/menu/soto-ayam-ambengan.svg',
                'is_available' => true,
            ],
            [
                'category_id' => $catMakanan->id,
                'name' => 'Soto Daging Sapi Bening Madura',
                'description' => 'Irisan daging sapi empuk dengan kuah kaldu rempah bening kaya rasa kapulaga dan cengkeh, dilengkapi tauge segar dan seledri.',
                'taste_profile' => 'Segar berkaldu gurih alami, hangat rempah',
                'ingredients' => ['Daging sapi sengkel', 'Tauge segar', 'Kuah kaldu sapi bening', 'Bawang putih goreng', 'Daun seledri'],
                'price' => 32000,
                'image_url' => '/images/menu/soto-daging-sapi.svg',
                'is_available' => true,
            ],
            [
                'category_id' => $catMakanan->id,
                'name' => 'Soto Mie Bogor Komplit',
                'description' => 'Potongan daging sapi empuk, risol bihun garing renyah, mie kuning kenyal, kol, tomat, dan kuah kaldu sapi merah gurih asam segar.',
                'taste_profile' => 'Segar asam gurih pedas manis, risol renyah',
                'ingredients' => ['Daging sapi', 'Risol bihun renyah', 'Mie kuning', 'Kol segar', 'Tomat', 'Kuah kaldu merah'],
                'price' => 28000,
                'image_url' => '/images/menu/soto-mie-bogor.svg',
                'is_available' => true,
            ],
            [
                'category_id' => $catMakanan->id,
                'name' => 'Soto Ayam Lamongan Koya Gurih',
                'description' => 'Suwiran ayam kampung empuk, soun lembut, kol segar, dan siraman kuah kuning gurih dengan taburan bubuk koya kelapa udang melimpah.',
                'taste_profile' => 'Gurih pekat koya, harum serai dan kunyit',
                'ingredients' => ['Ayam kampung suwir', 'Bubuk koya kelapa udang', 'Soun', 'Telur rebus', 'Kol segar', 'Jeruk nipis'],
                'price' => 24000,
                'image_url' => '/images/menu/soto-ayam-lamongan.svg',
                'is_available' => true,
            ],
            [
                'category_id' => $catMakanan->id,
                'name' => 'Soto Kudus Mangkuk Tradisional',
                'description' => 'Soto ayam khas Kudus dengan kuah kaldu rempah bening kecokelatan, tauge renyah, dan taburan bawang putih goreng harum khas mangkuk tanah liat.',
                'taste_profile' => 'Gurih manis rempah jintan, wangi bawang goreng',
                'ingredients' => ['Ayam suwir empuk', 'Tauge segar', 'Soun', 'Bawang putih goreng', 'Kuah kaldu rempah Kudus'],
                'price' => 25000,
                'image_url' => '/images/menu/soto-kudus.svg',
                'is_available' => true,
            ],

            // ================= 2. LAUK & TOPING TAMBAHAN =================
            [
                'category_id' => $catToping->id,
                'name' => 'Nasi Putih Pulen Wangi',
                'description' => 'Nasi putih pandan wangi hangat bertabur bawang goreng merah harum renyah.',
                'taste_profile' => 'Pulen lembut, harum pandan alami',
                'ingredients' => ['Beras premium pandan wangi', 'Bawang goreng merah'],
                'price' => 5000,
                'image_url' => '/images/menu/nasi-putih.svg',
                'is_available' => true,
            ],
            [
                'category_id' => $catToping->id,
                'name' => 'Sate Telur Puyuh Bacem',
                'description' => '4 butir telur puyuh masak bumbu bacem rempah kecap manis gurih meresap sampai ke dalam.',
                'taste_profile' => 'Manis gurih legit bumbu bacem',
                'ingredients' => ['Telur puyuh pilihan', 'Kecap manis kedelai hitam', 'Bumbu bacem rempah'],
                'price' => 5000,
                'image_url' => '/images/menu/sate-telur-puyuh.svg',
                'is_available' => true,
            ],
            [
                'category_id' => $catToping->id,
                'name' => 'Sate Usus Ayam Bacem',
                'description' => 'Usus ayam bersih olahan bumbu bacem gurih manis legit dipanggang wangi khas angkringan.',
                'taste_profile' => 'Kenyal manis gurih harum panggangan',
                'ingredients' => ['Usus ayam segar bersih', 'Bumbu bacem', 'Kecap manis'],
                'price' => 4000,
                'image_url' => '/images/menu/sate-usus-ayam.svg',
                'is_available' => true,
            ],
            [
                'category_id' => $catToping->id,
                'name' => 'Sate Ati Ampela Ungkep',
                'description' => 'Hati dan ampela ayam empuk yang diungkep dengan bumbu rempah kuning kunyit gurih meresap.',
                'taste_profile' => 'Gurih empuk harum bumbu ungkep',
                'ingredients' => ['Ati ampela ayam segar', 'Kunyit & lengkuas', 'Ketumbar', 'Bawang putih'],
                'price' => 5000,
                'image_url' => '/images/menu/sate-ati-ampela.svg',
                'is_available' => true,
            ],
            [
                'category_id' => $catToping->id,
                'name' => 'Perkedel Kentang Asli',
                'description' => 'Perkedel olahan kentang asli lembut dengan bumbu rempah dan balutan telur goreng keemasan.',
                'taste_profile' => 'Lembut gurih lumer di lidah',
                'ingredients' => ['Kentang asli kukus', 'Telur ayam', 'Daun seledri', 'Bawang goreng'],
                'price' => 4000,
                'image_url' => '/images/menu/perkedel-kentang.svg',
                'is_available' => true,
            ],
            [
                'category_id' => $catToping->id,
                'name' => 'Emping Melinjo Renyah',
                'description' => 'Emping melinjo goreng garing dengan cita rasa khas sedikit pahit gurih asin, teman setia menyantap soto.',
                'taste_profile' => 'Renyah garing khas melinjo murni',
                'ingredients' => ['Emping melinjo murni', 'Garam alami'],
                'price' => 6000,
                'image_url' => '/images/menu/emping-melinjo.svg',
                'is_available' => true,
            ],
            [
                'category_id' => $catToping->id,
                'name' => 'Telur Asin Brebes Masir',
                'description' => 'Telur bebek asin khas Brebes dengan kuning telur berminyak (masir) dan rasa gurih asin pas.',
                'taste_profile' => 'Asin gurih masir berminyak',
                'ingredients' => ['Telur bebek pilihan Brebes', 'Garam bata merah alami'],
                'price' => 6000,
                'image_url' => '/images/menu/telur-asin.svg',
                'is_available' => true,
            ],
            [
                'category_id' => $catToping->id,
                'name' => 'Kerupuk Kaleng Putih (Blekk)',
                'description' => 'Kerupuk mawar putih renyah garing, sedap dicelup ke dalam kuah soto hangat.',
                'taste_profile' => 'Renyah garing gurih',
                'ingredients' => ['Tepung tapioka', 'Bawang putih', 'Garam'],
                'price' => 2000,
                'image_url' => '/images/menu/kerupuk-kaleng.svg',
                'is_available' => true,
            ],

            // ================= 3. MINUMAN SEGAR =================
            [
                'category_id' => $catMinuman->id,
                'name' => 'Es Teh Manis Melati',
                'description' => 'Seduhan teh melati tradisional wangi pekat berpadu es batu kristal segar dan gula tebu asli.',
                'taste_profile' => 'Manis dingin segar harum bunga melati',
                'ingredients' => ['Daun teh melati tradisional', 'Gula tebu murni', 'Es batu kristal'],
                'price' => 5000,
                'image_url' => '/images/menu/es-teh-manis.svg',
                'is_available' => true,
            ],
            [
                'category_id' => $catMinuman->id,
                'name' => 'Es Teh Tawar Melati',
                'description' => 'Teh tawar dingin dengan aroma melati alami menyegarkan, cocok untuk menetralisir kuah santan.',
                'taste_profile' => 'Dingin segar sejuk pekat alami',
                'ingredients' => ['Daun teh melati murni', 'Es batu kristal'],
                'price' => 3000,
                'image_url' => '/images/menu/es-teh-tawar.svg',
                'is_available' => true,
            ],
            [
                'category_id' => $catMinuman->id,
                'name' => 'Es Jeruk Peras Segar',
                'description' => 'Perasan jeruk asli manis alami dengan bulir jeruk segar berpadu es kristal dingin yang menyegarkan.',
                'taste_profile' => 'Manis asam segar alami jeruk peras',
                'ingredients' => ['Jeruk peras segar pilihan', 'Gula tebu asli', 'Es batu kristal'],
                'price' => 8000,
                'image_url' => '/images/menu/es-jeruk.svg',
                'is_available' => true,
            ],
            [
                'category_id' => $catMinuman->id,
                'name' => 'Jeruk Hangat Murni',
                'description' => 'Sari jeruk segar asli diseduh air hangat yang nyaman di tenggorokan, sangat pas disandingkan soto hangat.',
                'taste_profile' => 'Asam manis hangat menenangkan',
                'ingredients' => ['Jeruk peras murni', 'Gula tebu', 'Air hangat'],
                'price' => 8000,
                'image_url' => '/images/menu/jeruk-hangat.svg',
                'is_available' => true,
            ],
            [
                'category_id' => $catMinuman->id,
                'name' => 'Es Cendol Santan Betawi',
                'description' => 'Cendol pandan lembut disajikan dengan siraman santan kelapa gurih, gula aren cair wangi, dan es serut dingin.',
                'taste_profile' => 'Manis legit gula aren, gurih creamy santan kelapa',
                'ingredients' => ['Cendol pandan suji', 'Gula aren murni', 'Santan kelapa segar', 'Es serut'],
                'price' => 12000,
                'image_url' => '/images/menu/es-cendol.svg',
                'is_available' => true,
            ],
            [
                'category_id' => $catMinuman->id,
                'name' => 'Air Mineral Dingin (600ml)',
                'description' => 'Air mineral pegunungan botol 600ml dingin higienis dan menyegarkan.',
                'taste_profile' => 'Dingin segar alami',
                'ingredients' => ['Air mineral pegunungan 100%'],
                'price' => 4000,
                'image_url' => '/images/menu/air-mineral.svg',
                'is_available' => true,
            ],
        ];

        foreach ($menus as $menu) {
            Product::updateOrCreate(
                [
                    'name' => $menu['name'],
                ],
                [
                    'category_id' => $menu['category_id'],
                    'description' => $menu['description'],
                    'taste_profile' => $menu['taste_profile'],
                    'ingredients' => $menu['ingredients'],
                    'price' => $menu['price'],
                    'image_url' => $menu['image_url'],
                    'is_available' => $menu['is_available'],
                ]
            );
        }
    }
}
