<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StoreSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'value',
    ];

    /**
     * Default store settings map.
     *
     * @return array<string, string>
     */
    public static function defaults(): array
    {
        return [
            'store_name' => 'Soto Betawi Parsoey',
            'store_tagline' => 'Cita Rasa Gurih Kuah Santan & Susu Rempah Asli Betawi',
            'store_branch' => 'Cabang Utama - POS 01',
            'store_address' => 'Jl. Raya Betawi No. 88, Jakarta Selatan',
            'store_phone' => '0812-9876-5432',
            'store_instagram' => '@sotoparsoey.id',
            'wifi_ssid' => 'SOTO PARSOEY FREE',
            'wifi_password' => 'sotobetawi88',
            'receipt_footer' => "Terima kasih atas kunjungan Anda!\nKritik & saran via WhatsApp: 0812-9876-5432\nSemoga harimu menyenangkan!",
            'tax_percentage' => '0',
        ];
    }

    /**
     * Get a single setting by key.
     */
    public static function get(string $key, ?string $default = null): ?string
    {
        $setting = static::where('key', $key)->first();
        if ($setting && $setting->value !== null) {
            return $setting->value;
        }
        return $default ?? static::defaults()[$key] ?? null;
    }

    /**
     * Set a single setting by key.
     */
    public static function set(string $key, ?string $value): void
    {
        static::updateOrCreate(
            ['key' => $key],
            ['value' => $value]
        );
    }

    /**
     * Get all settings as an associative key-value map with fallback defaults.
     *
     * @return array<string, string>
     */
    public static function getAllAsMap(): array
    {
        $dbSettings = static::pluck('value', 'key')->all();
        return array_merge(static::defaults(), $dbSettings);
    }

    /**
     * Set multiple settings from an array.
     *
     * @param array<string, mixed> $settings
     */
    public static function setMany(array $settings): void
    {
        foreach ($settings as $key => $value) {
            static::set((string) $key, (string) ($value ?? ''));
        }
    }
}
