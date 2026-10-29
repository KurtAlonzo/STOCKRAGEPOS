import { expect, test } from 'vite-plus/test';
import {
    catalogFixture,
    inventoryFixture,
    mixedBasketFixture,
    contractScenarios,
    recoveryExamples,
} from './scenarios';
// Exact thousandths for checking fixtures, not a production quantity parser.
const scaled = (value: string) => BigInt(value.replace('.', ''));
test('mixed_basket_total_and_change', () => {
    const total = mixedBasketFixture.quoteInput.lines.reduce((sum, line) => {
        const product = catalogFixture.find((p) => p.id === line.productId)!;
        return (
            sum +
            line.quantity *
                (product.priceMinor +
                    line.addonIds.reduce(
                        (s, id) =>
                            s +
                            catalogFixture.find((p) => p.id === id)!.priceMinor,
                        0,
                    ))
        );
    }, 0);
    expect(total).toBe(41000);
    expect(mixedBasketFixture.quote.totalMinor).toBe(total);
    expect(mixedBasketFixture.sale.totalMinor).toBe(total);
    expect(mixedBasketFixture.quote.changeMinor).toBe(9000);
    expect(mixedBasketFixture.sale.stockMovements).toHaveLength(6);
});
test('shared_components_aggregate_across_lines_and_addons', () => {
    const demands = new Map<number, bigint>();
    for (const line of mixedBasketFixture.quoteInput.lines) {
        for (const id of [line.productId, ...line.addonIds]) {
            const item = catalogFixture.find((p) => p.id === id)!;
            const parts =
                'components' in item
                    ? item.components
                    : [
                          {
                              inventoryItemId: item.stockItemId,
                              quantity: '1.000',
                          },
                      ];
            for (const part of parts)
                demands.set(
                    part.inventoryItemId,
                    (demands.get(part.inventoryItemId) ?? 0n) +
                        scaled(part.quantity) * BigInt(line.quantity),
                );
        }
    }
    expect(demands.get(203)).toBe(350000n);
    expect(demands.get(205)).toBe(375n);
    expect(demands.size).toBe(6);
    for (const effect of mixedBasketFixture.quote.stockEffects) {
        expect(scaled(effect.demand)).toBe(demands.get(effect.inventoryItemId));
        expect(scaled(effect.after)).toBe(
            scaled(effect.before) - scaled(effect.demand),
        );
        const inventory = inventoryFixture.find(
            (i) => i.id === effect.inventoryItemId,
        )!;
        expect(effect.before).toBe(inventory.balance);
        expect(effect.policy).toBe(inventory.shortagePolicy);
    }
    expect(
        mixedBasketFixture.quote.warnings.map((w) => w.inventoryItemId),
    ).toEqual([203, 204, 206]);
});
test('direct_stock_boundaries', () => {
    for (const [id, boundary] of [
        [201, 2],
        [202, 3],
    ]) {
        const stock = inventoryFixture.find((i) => i.id === id)!;
        expect(stock.shortagePolicy).toBe('block');
        expect(scaled(stock.balance) - BigInt(boundary) * 1000n).toBe(0n);
        expect(
            scaled(stock.balance) - BigInt(boundary + 1) * 1000n,
        ).toBeLessThan(0n);
    }
});
test('measured_precision_and_counted_units', () => {
    for (const item of inventoryFixture) {
        expect(item.balance).toMatch(/^-?\d+\.\d{3}$/);
        if (item.unit === 'each') expect(scaled(item.balance) % 1000n).toBe(0n);
    }
    expect(
        contractScenarios
            .filter((s) =>
                [
                    'fractional-sale',
                    'fractional-each',
                    'excess-precision',
                    'invalid-money',
                ].includes(s.id),
            )
            .every((s) => s.httpStatus === 422),
    ).toBe(true);
    expect(
        contractScenarios.find((s) => s.id === 'excess-precision')!.request,
    ).toEqual({ quantity: '0.0001' });
});
test('optional_reference_and_duplicate_warning', () => {
    for (const method of ['gcash', 'maya'])
        expect(
            contractScenarios.find((s) => s.id === method + '-no-reference')!
                .request,
        ).toEqual({ method, receivedConfirmed: true, reference: null });
    expect(
        contractScenarios.find((s) => s.id === 'duplicate-reference')!.expected,
    ).toBe('review_then_allow');
    expect(
        contractScenarios.find((s) => s.id === 'unconfirmed-digital')!
            .httpStatus,
    ).toBe(422);
});
test('revision_changes_require_review', () => {
    expect(5000 + 2 * (11000 + 1500) + 11000 + 3000).toBe(44000);
    expect(3n * 110000n + 50000n).toBe(380000n);
    expect(250000n - 380000n).toBe(-130000n);
    expect(
        contractScenarios.find((s) => s.id === 'quote_changed')!.response,
    ).toMatchObject({
        replacementQuote: { revision: 'q2', totalMinor: 44000 },
    });
});
test('attempt_examples_preserve_original_key', () => {
    const original = mixedBasketFixture.completionInput;
    for (const step of recoveryExamples) {
        expect(step.request).toEqual(original);
        if (step.result.status === 'confirmed') {
            expect(step.result.idempotencyKey).toBe(original.idempotencyKey);
            expect(step.result.sale.id).toBe(1);
        }
    }
    expect(recoveryExamples.map((s) => s.result.status)).toEqual([
        'not_found',
        'pending',
        'confirmed',
        'confirmed',
        'rejected',
    ]);
    expect(
        contractScenarios.find((s) => s.id === 'idempotency_conflict')!
            .httpStatus,
    ).toBe(409);
});
test('forbidden_store_examples_do_not_leak_records', () => {
    for (const id of ['nonowner', 'forbidden-store', 'foreign-resource']) {
        const scenario = contractScenarios.find((s) => s.id === id)!;
        expect(scenario.httpStatus).toBe(id === 'foreign-resource' ? 404 : 403);
        expect(scenario.response).not.toHaveProperty('sale');
        expect(scenario.response).not.toHaveProperty('lines');
    }
});

test('replacement_quote_lines_explain_its_total', () => {
    const response = contractScenarios.find((s) => s.id === 'quote_changed')!
        .response as { replacementQuote: typeof mixedBasketFixture.quote };
    expect(
        response.replacementQuote.lines.reduce(
            (sum, line) => sum + line.totalMinor,
            0,
        ),
    ).toBe(response.replacementQuote.totalMinor);
});

test('conflicting_key_changes_the_payload', () => {
    const s = contractScenarios.find((s) => s.id === 'idempotency_conflict')!;
    expect(s.request).not.toEqual(mixedBasketFixture.completionInput);
    expect(s.request).toHaveProperty(
        'idempotencyKey',
        mixedBasketFixture.completionInput.idempotencyKey,
    );
});
test('addon_snapshots_preserve_original_stock_mapping', () => {
    expect(mixedBasketFixture.sale.lines[1].addons[0]).toHaveProperty(
        'directStockItemId',
        202,
    );
});
test('cash_examples_cover_insufficient_exact_and_over_tender', () => {
    expect(
        contractScenarios.find((s) => s.id === 'cash-insufficient')!.httpStatus,
    ).toBe(422);
    expect(
        contractScenarios.find((s) => s.id === 'cash-exact')!.response,
    ).toMatchObject({ changeMinor: 0 });
    expect(
        contractScenarios.find((s) => s.id === 'cash-over')!.response,
    ).toMatchObject({ changeMinor: 9000 });
});
