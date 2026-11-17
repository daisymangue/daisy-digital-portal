<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Storage;

/** The uploaded logo, if there is one. Kept on the private disk and served through a route. */
class Brand
{
    public static function hasLogo(): bool
    {
        $path = Setting::get('logo_path');

        return $path !== '' && Storage::disk('local')->exists($path);
    }

    public static function logoUrl(): ?string
    {
        return self::hasLogo() ? route('brand.logo', ['v' => substr(md5(Setting::get('logo_path')), 0, 8)]) : null;
    }

    /** For PDFs, which cannot fetch URLs: the image inline as a data URI. */
    public static function logoDataUri(): ?string
    {
        if (! self::hasLogo()) {
            return null;
        }
        $path = Setting::get('logo_path');

        return 'data:'.Storage::disk('local')->mimeType($path).';base64,'.base64_encode(Storage::disk('local')->get($path));
    }
}
