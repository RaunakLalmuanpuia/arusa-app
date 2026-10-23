<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

#[Fillable(['key', 'value'])]
class Setting extends Model
{
    /**
     * Values used until the admin saves their own.
     *
     * @var array{store_name: string, contact_phone: ?string, whatsapp_number: ?string, contact_email: ?string, cod_enabled: string}
     */
    public const DEFAULTS = [
        'store_name' => 'Arusa',
        'contact_phone' => null,
        'whatsapp_number' => null,
        'contact_email' => null,
        'cod_enabled' => '1',
    ];

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget('settings'));
    }

    /**
     * All settings, defaults merged with stored values.
     *
     * @return array<string, ?string>
     */
    public static function values(): array
    {
        return array_merge(
            self::DEFAULTS,
            Cache::rememberForever('settings', fn (): array => static::pluck('value', 'key')->all()),
        );
    }
}
