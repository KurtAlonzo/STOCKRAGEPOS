<?php

namespace App\Support;

use Illuminate\Support\Str;

/** Which picture a product without its own photo gets. */
class MenuArt
{
    public const KINDS = ['bacon', 'overload', 'mix', 'cheese', 'crab', 'octobits', 'classic'];

    public static function kind(string $name): string
    {
        $n = Str::lower($name);

        return match (true) {
            Str::contains($n, 'bacon') => 'bacon',
            Str::contains($n, 'overload') => 'overload',
            Str::contains($n, 'mix') => 'mix',
            Str::contains($n, 'cheese') => 'cheese',
            Str::contains($n, 'crab') => 'crab',
            Str::contains($n, 'octo') => 'octobits',
            default => 'classic',
        };
    }

    /**
     * A real photo dropped into public/images/menu/ is used instead of the drawing:
     * bacon.jpg, mix.jpg, overload.jpg, classic.jpg (.jpeg .png .webp also work).
     */
    public static function photoUrl(string $kind): ?string
    {
        foreach (['jpg', 'jpeg', 'png', 'webp'] as $ext) {
            if (is_file(public_path("images/menu/{$kind}.{$ext}"))) {
                return asset("images/menu/{$kind}.{$ext}");
            }
        }

        return null;
    }
}
