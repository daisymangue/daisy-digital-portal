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
            // The built-in navy version, which reads on white paper.
            $default = public_path('images/logo-dark.png');

            return is_file($default) ? 'data:image/png;base64,'.base64_encode(file_get_contents($default)) : null;
        }
        $path = Setting::get('logo_path');

        return 'data:'.Storage::disk('local')->mimeType($path).';base64,'.base64_encode(Storage::disk('local')->get($path));
    }
}
