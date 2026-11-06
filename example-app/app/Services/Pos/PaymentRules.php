<?php

namespace App\Services\Pos;

use App\Models\Payment;
use App\Models\Sale;
use App\Models\Team;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

final class PaymentRules
{
    /** @return list<string> */
    public function enabledMethods(Team $store): array
    {
        return config('pos.store_payment_methods.'.$store->id, config('pos.payment_methods', ['cash', 'gcash', 'maya']));
    }

    /** @return array<string, mixed>|null */
    public function validate(Team $store, mixed $input, int $totalMinor): ?array
    {
        if ($input === null) {
            return null;
        }
        Validator::make(['payment' => $input], ['payment' => 'required|array', 'payment.method' => ['required', Rule::in($this->enabledMethods($store))]])->validate();
        if ($input['method'] === 'cash') {
            Validator::make($input, ['tenderedMinor' => ['required', 'integer', 'min:'.$totalMinor, 'max:9007199254740991']])->validate();

            return ['method' => 'cash', 'tenderedMinor' => (int) $input['tenderedMinor']];
        }
        Validator::make($input, ['receivedConfirmed' => ['required', function ($attribute, $value, $fail) {
            if ($value !== true) {
                $fail('Confirm receipt outside the app.');
            }
        }], 'reference' => 'present|nullable|string|max:255'])->validate();

        return ['method' => $input['method'], 'receivedConfirmed' => true, 'reference' => (trim($input['reference'] ?? '') === '' ? null : trim($input['reference']))];
    }

    /**
     * @param  array<string, mixed>|null  $payment
     * @return list<array<string, mixed>>
     */
    public function warnings(Team $store, ?array $payment): array
    {
        if (! $payment || $payment['method'] === 'cash' || $payment['reference'] === null) {
            return [];
        }
        // Compare in PHP so reference identity is case-sensitive on every database collation.
        $references = Payment::whereIn('sale_id', Sale::where('team_id', $store->id)->select('id'))->where('method', $payment['method'])->whereNotNull('reference')->pluck('reference');
        if (! $references->containsStrict($payment['reference'])) {
            return [];
        }

        return [['id' => 'duplicate-reference-'.$payment['method'], 'code' => 'duplicate_payment_reference', 'message' => 'This '.$payment['method'].' reference has already been recorded in this store. Check the external receipt.', 'field' => 'payment.reference', 'inventoryItemId' => null]];
    }
}
