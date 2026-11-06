<?php

namespace App\Services\Pos;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class CheckoutRequest
{
    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public static function canonical(array $input): array
    {
        Validator::make($input, ['idempotencyKey' => 'required|uuid', 'quoteId' => 'required|uuid', 'quoteRevision' => 'required|string|max:100', 'lines' => 'required|array|min:1', 'lines.*.id' => 'required|uuid|distinct:strict', 'lines.*.productId' => 'required|integer|min:1', 'lines.*.quantity' => 'required|integer|min:1|max:9007199254740', 'lines.*.addonIds' => 'present|array', 'lines.*.addonIds.*' => 'required|integer|min:1', 'payment' => 'required|array', 'payment.method' => 'required|in:cash,gcash,maya', 'acknowledgedWarningIds' => 'present|array', 'acknowledgedWarningIds.*' => 'required|string|distinct:strict'])->validate();
        $lines = [];
        foreach ($input['lines'] as $line) {
            $addons = array_map('intval', $line['addonIds']);
            Validator::make(['addons' => $addons], ['addons.*' => 'distinct:strict'])->validate();
            sort($addons, SORT_NUMERIC);
            $lines[] = ['id' => $line['id'], 'productId' => (int) $line['productId'], 'quantity' => (int) $line['quantity'], 'addonIds' => $addons];
        }
        $payment = $input['payment'];
        if ($payment['method'] === 'cash') {
            Validator::make($payment, ['tenderedMinor' => 'required|integer|min:0|max:9007199254740991'])->validate();
            $payment = ['method' => 'cash', 'tenderedMinor' => (int) $payment['tenderedMinor']];
        } else {
            Validator::make($payment, ['receivedConfirmed' => 'required|accepted', 'reference' => 'present|nullable|string|max:255'])->validate();
            if ($payment['receivedConfirmed'] !== true) {
                throw ValidationException::withMessages(['payment.receivedConfirmed' => 'Confirm external receipt.']);
            }
            $payment = ['method' => $payment['method'], 'receivedConfirmed' => true, 'reference' => (trim($payment['reference'] ?? '') === '' ? null : trim($payment['reference']))];
        }
        $warnings = $input['acknowledgedWarningIds'];
        sort($warnings, SORT_STRING);

        return ['idempotencyKey' => $input['idempotencyKey'], 'quoteId' => $input['quoteId'], 'quoteRevision' => $input['quoteRevision'], 'lines' => $lines, 'payment' => $payment, 'acknowledgedWarningIds' => $warnings];
    }

    /** @param array<string, mixed> $input */
    public static function hash(array $input): string
    {
        return hash('sha256', json_encode(self::canonical($input), JSON_THROW_ON_ERROR));
    }
}
