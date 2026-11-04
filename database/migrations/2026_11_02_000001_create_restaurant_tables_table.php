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
        Schema::create('restaurant_tables', function (Blueprint $table): void {
            $table->id();
            $table->string('table_number', 20)->unique();
            $table->string('name', 50);
            $table->unsignedInteger('capacity')->default(4);
            $table->string('status', 20)->default('available'); // 'available' | 'occupied'
            $table->foreignId('current_order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->timestamps();
        });

        // Seed 12 default tables for Soto Betawi Parsoey
        $defaultTables = [
            ['table_number' => '01', 'name' => 'Meja 01', 'capacity' => 2, 'status' => 'available'],
            ['table_number' => '02', 'name' => 'Meja 02', 'capacity' => 4, 'status' => 'available'],
            ['table_number' => '03', 'name' => 'Meja 03', 'capacity' => 4, 'status' => 'available'],
            ['table_number' => '04', 'name' => 'Meja 04', 'capacity' => 4, 'status' => 'available'],
            ['table_number' => '05', 'name' => 'Meja 05', 'capacity' => 6, 'status' => 'available'],
            ['table_number' => '06', 'name' => 'Meja 06', 'capacity' => 4, 'status' => 'available'],
            ['table_number' => '07', 'name' => 'Meja 07', 'capacity' => 4, 'status' => 'available'],
            ['table_number' => '08', 'name' => 'Meja 08', 'capacity' => 2, 'status' => 'available'],
            ['table_number' => '09', 'name' => 'Meja 09', 'capacity' => 4, 'status' => 'available'],
            ['table_number' => '10', 'name' => 'Meja 10', 'capacity' => 4, 'status' => 'available'],
            ['table_number' => '11', 'name' => 'Meja 11', 'capacity' => 6, 'status' => 'available'],
            ['table_number' => '12', 'name' => 'Meja 12', 'capacity' => 4, 'status' => 'available'],
        ];

        $now = now();
        foreach ($defaultTables as &$tbl) {
            $tbl['created_at'] = $now;
            $tbl['updated_at'] = $now;
        }

        DB::table('restaurant_tables')->insert($defaultTables);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('restaurant_tables');
    }
};
