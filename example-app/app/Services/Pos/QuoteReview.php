<?php

namespace App\Services\Pos;

final class QuoteReview
{
    /**
     * @param  array<string, mixed>  $reviewed
     * @param  array<string, mixed>  $current
     */
    public static function changed(array $reviewed, array $current): bool
    {
        foreach (['lines', 'subtotalMinor', 'totalMinor', 'payment', 'changeMinor', 'warnings', 'blockingIssues'] as $field) {
            if (($reviewed[$field] ?? null) != ($current[$field] ?? null)) {
                return true;
            }
        }

        return false;
    }
}
