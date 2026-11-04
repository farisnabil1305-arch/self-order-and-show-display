<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->enum('kitchen_status', ['queued', 'cooking', 'ready', 'completed'])
                ->default('queued')
                ->after('status');
            $table->timestamp('cooking_started_at')->nullable()->after('kitchen_status');
            $table->timestamp('ready_at')->nullable()->after('cooking_started_at');
            $table->timestamp('completed_at')->nullable()->after('ready_at');
            $table->unsignedInteger('call_counter')->default(0)->after('completed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn([
                'kitchen_status',
                'cooking_started_at',
                'ready_at',
                'completed_at',
                'call_counter',
            ]);
        });
    }
};
