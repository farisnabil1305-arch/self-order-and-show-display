<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('store_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        // Seed default store settings
        $defaults = [
            'store_name' => 'Soto Betawi Parsoey',
            'store_tagline' => 'Cita Rasa Gurih Kuah Santan & Susu Rempah Asli Betawi',
            'store_branch' => 'Cabang Utama - POS 01',
            'store_address' => 'Jl. Raya Betawi No. 88, Jakarta Selatan',
            'store_phone' => '0812-9876-5432',
            'store_instagram' => '@sotoparsoey.id',
            'receipt_footer' => "Terima kasih atas kunjungan Anda!\nKritik & saran via WhatsApp: 0812-9876-5432\nSemoga harimu menyenangkan!",
            'tax_percentage' => '0',
        ];

        $now = now();
        $records = [];
        foreach ($defaults as $k => $v) {
            $records[] = [
                'key' => $k,
                'value' => $v,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::table('store_settings')->insert($records);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('store_settings');
    }
};
