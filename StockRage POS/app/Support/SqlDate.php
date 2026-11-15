<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Small helper so the report queries work on MySQL (production) and
 * SQLite (automated tests). Returns a raw SQL expression for a column.
 */
class SqlDate
{
    /** @param 'day'|'month'|'year' $unit */
    public static function key(string $column, string $unit): string
    {
        $driver = DB::connection()->getDriverName();

        $formats = [
            'day' => ['mysql' => '%Y-%m-%d', 'sqlite' => '%Y-%m-%d'],
            'month' => ['mysql' => '%Y-%m', 'sqlite' => '%Y-%m'],
            'year' => ['mysql' => '%Y', 'sqlite' => '%Y'],
        ];

        if ($driver === 'sqlite') {
            return "strftime('{$formats[$unit]['sqlite']}', {$column})";
        }

        return "DATE_FORMAT({$column}, '{$formats[$unit]['mysql']}')";
    }
}
