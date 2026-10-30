<?php

namespace App\Support;

use App\Models\Team;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class StoreCatalogTransaction
{
    public static function run(Team $store, Closure $write): mixed
    {
        for ($attempt = 0; $attempt < 3; $attempt++) {
            try {
                return DB::transaction(function () use ($store, $write) {
                    Team::whereKey($store->id)->lockForUpdate()->firstOrFail();

                    return $write();
                });
            } catch (QueryException $error) {
                if (! preg_match('/locked|busy|deadlock|serialization/i', $error->getMessage())) {
                    throw $error;
                }
            }
        }
        throw ValidationException::withMessages(['expectedRevision' => 'This record is being updated. Reload and try again.']);
    }
}
