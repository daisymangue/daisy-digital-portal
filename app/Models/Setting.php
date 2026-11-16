<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Business settings the admin edits in the portal: details for documents, VAT, quote defaults. */
class Setting extends Model
{
    public $incrementing = false;

    protected $primaryKey = 'key';

    protected $keyType = 'string';

    protected $fillable = ['key', 'value'];

    public const DEFAULTS = [
        'business_name' => 'Daisy Digital Agency',
        'business_address' => '',
        'business_email' => '',
        'business_phone' => '',
        'business_website' => 'www.daisydigitalagency.com',
        'registration_number' => '',
        'vat_number' => '',
        'vat_rate' => '15',
        'quote_valid_days' => '30',
        'deposit_percent' => '50',
        'invoice_due_days' => '7',
        'bank_details' => '',
        'quote_terms' => "This quote is valid until the date shown above.\nWork starts once the quote is accepted and the deposit is received.\nWork outside the scope above is quoted separately.",
    ];

    private static ?array $cache = null;

    public static function values(): array
    {
        return self::$cache ??= array_merge(self::DEFAULTS, self::query()->pluck('value', 'key')->map(fn ($v) => (string) $v)->all());
    }

    public static function get(string $key): string
    {
        return self::values()[$key] ?? '';
    }

    public static function put(array $values): void
    {
        foreach (array_intersect_key($values, self::DEFAULTS) as $key => $value) {
            self::updateOrCreate(['key' => $key], ['value' => (string) ($value ?? '')]);
        }
        self::$cache = null;
    }

    public static function flush(): void
    {
        self::$cache = null;
    }
}
