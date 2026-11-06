<?php

namespace App\Support;

use Illuminate\Validation\ValidationException;

final class Quantity
{
    public const MAX = 9007199254740991;

    public static function parse(string $value, string $unit, bool $allowNegative = false): int
    {
        if (! in_array($unit, ['each', 'g', 'ml'], true) || ! preg_match('/^(-?)(\d+)(?:\.(\d{1,3}))?$/D', $value, $parts)) {
            self::invalid();
        }
        $digits = ltrim($parts[2].str_pad($parts[3] ?? '', 3, '0'), '0') ?: '0';
        $max = (string) self::MAX;
        if (strlen($digits) > strlen($max) || (strlen($digits) === strlen($max) && strcmp($digits, $max) > 0)) {
            self::invalid('numeric_range_exceeded');
        }
        $scaled = (int) $digits;
        if (($parts[1] === '-' && ! $allowNegative) || ($unit === 'each' && $scaled % 1000 !== 0)) {
            self::invalid();
        }

        return $parts[1] === '-' ? -$scaled : $scaled;
    }

    public static function format(int $scaled): string
    {
        return ($scaled < 0 ? '-' : '').intdiv(abs($scaled), 1000).'.'.str_pad((string) (abs($scaled) % 1000), 3, '0', STR_PAD_LEFT);
    }

    public static function add(int $a, int $b): int
    {
        if (abs($a) > self::MAX || abs($b) > self::MAX || ($b > 0 && $a > self::MAX - $b) || ($b < 0 && $a < -self::MAX - $b)) {
            self::invalid('numeric_range_exceeded');
        }

        return $a + $b;
    }

    public static function multiply(int $scaled, int $count): int
    {
        if ($count < 0 || $count > self::MAX || abs($scaled) > self::MAX || ($count > 0 && abs($scaled) > intdiv(self::MAX, $count))) {
            self::invalid('numeric_range_exceeded');
        }

        return $scaled * $count;
    }

    private static function invalid(string $message = 'Enter a valid quantity in the configured base unit, with at most three decimals.'): never
    {
        throw ValidationException::withMessages(['quantity' => $message]);
    }
}
