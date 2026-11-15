<?php

namespace App\Support;

use App\Models\Setting;

/** Accent colour themes. Keys must match the palettes in tailwind.config.js. */
class Theme
{
    public const DEFAULT = 'red';

    /** key => [label, swatch hex (the 500 shade), browser bar colour] */
    public static function all(): array
    {
        return [
            'red' => ['Stockrage Red', '#ee2b33'],
            'orange' => ['Takoyaki Orange', '#f97316'],
            'green' => ['Matcha Green', '#22c55e'],
            'teal' => ['Teal', '#14b8a6'],
            'blue' => ['Blue', '#3b82f6'],
            'violet' => ['Violet', '#8b5cf6'],
            'pink' => ['Pink', '#ec4899'],
        ];
    }

    public static function current(): string
    {
        $key = (string) Setting::get('theme_color', self::DEFAULT);

        return array_key_exists($key, self::all()) ? $key : self::DEFAULT;
    }

    public static function swatch(string $key): string
    {
        return self::all()[$key][1] ?? self::all()[self::DEFAULT][1];
    }
}
