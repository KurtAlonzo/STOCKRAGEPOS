<?php

namespace App\Services\Sales;

use App\Support\Quantity;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class ReversalRequest
{
    /** @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public static function canonical(int $saleId, array $input): array
    {
        foreach (['reason', 'refundReference'] as $field) {
            if (isset($input[$field]) && is_string($input[$field])) {
                $input[$field] = trim($input[$field]);
            }
        }
        Validator::make($input, ['idempotencyKey' => 'required|uuid', 'reason' => 'required|string|max:1000', 'refundMethod' => 'required|in:cash,gcash,maya', 'refundReference' => 'present|nullable|string|max:255', 'refundConfirmed' => ['required', fn ($attribute, $value, $fail) => $value === true ?: $fail('Confirm the external refund.')], 'restock' => 'present|array', 'restock.*.inventoryItemId' => 'required|integer|min:1|distinct', 'restock.*.quantity' => 'required|string'])->validate();
        $restock = [];
        foreach ($input['restock'] as $index => $part) {
            try {
                $quantity = Quantity::parse($part['quantity'], 'each');
            } catch (ValidationException $error) {
                throw ValidationException::withMessages(['restock.'.$index.'.quantity' => $error->errors()['quantity']]);
            }
            if ($quantity <= 0) {
                throw ValidationException::withMessages(['restock.'.$index.'.quantity' => 'Return a positive whole quantity.']);
            }
            $restock[] = ['inventoryItemId' => (int) $part['inventoryItemId'], 'quantity' => Quantity::format($quantity)];
        }
        usort($restock, fn ($a, $b) => $a['inventoryItemId'] <=> $b['inventoryItemId']);

        return ['saleId' => $saleId, 'idempotencyKey' => $input['idempotencyKey'], 'reason' => $input['reason'], 'refundConfirmed' => true, 'refundMethod' => $input['refundMethod'], 'refundReference' => ($input['refundReference'] ?? '') === '' ? null : $input['refundReference'], 'restock' => $restock];
    }

    /** @param array<string, mixed> $canonical */
    public static function hash(array $canonical): string
    {
        return hash('sha256', json_encode($canonical, JSON_THROW_ON_ERROR));
    }
}
