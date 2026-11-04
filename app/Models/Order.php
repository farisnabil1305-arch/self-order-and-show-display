<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'order_number',
        'customer_name',
        'table_number',
        'order_type',
        'subtotal',
        'tax',
        'total_amount',
        'payment_method',
        'amount_paid',
        'change_amount',
        'status',
        'kitchen_status',
        'cooking_started_at',
        'ready_at',
        'completed_at',
        'call_counter',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'subtotal' => 'integer',
            'tax' => 'integer',
            'total_amount' => 'integer',
            'amount_paid' => 'integer',
            'change_amount' => 'integer',
            'call_counter' => 'integer',
            'cooking_started_at' => 'datetime',
            'ready_at' => 'datetime',
            'completed_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Get the items for the order.
     *
     * @return HasMany<OrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Generate the next unique order number for the current day (e.g. SOTO-001).
     */
    public static function generateNextOrderNumber(): string
    {
        $today = Carbon::today();
        $countToday = static::whereDate('created_at', $today)->count();
        $sequence = $countToday + 1;

        $orderNumber = sprintf('SOTO-%03d', $sequence);

        // Ensure uniqueness just in case of race conditions
        while (static::where('order_number', $orderNumber)->exists()) {
            $sequence++;
            $orderNumber = sprintf('SOTO-%03d', $sequence);
        }

        return $orderNumber;
    }
}
