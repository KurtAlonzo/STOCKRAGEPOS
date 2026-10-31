import { describe, expect, it } from 'vite-plus/test';
import {
    catalogFixture,
    inventoryFixture,
    mixedBasketFixture,
} from '../../fixtures/pos/scenarios';
import {
    addLine,
    setLineAddons,
    setLineQuantity,
    removeLine,
    estimateCart,
    normalizeAddonIds,
} from './cart';
const lines = mixedBasketFixture.quoteInput.lines;
const data = {
    catalog: new Map(catalogFixture.map((x) => [x.id, x])),
    inventory: new Map(inventoryFixture.map((x) => [x.id, x])),
};
describe('cart preparation', () => {
    it('matches the exact mixed basket without changing input', () => {
        const before = JSON.stringify(lines);
        const result = estimateCart(lines, data);
        expect(result.totalMinor).toBe(41000);
        expect(result.stockEffects.map((x) => x.after)).toEqual([
            '1.000',
            '1.000',
            '-100.000',
            '-35.000',
            '0.625',
            '-1.000',
        ]);
        expect(result.blockingIssues).toEqual([]);
        expect(result.warnings).toHaveLength(3);
        expect(JSON.stringify(lines)).toBe(before);
    });
    it('merges configurations and retains the existing target identity', () => {
        const incoming = { ...lines[1], id: lines[2].id, addonIds: [104, 103] };
        const first = addLine([], incoming);
        expect(
            addLine(first, {
                ...incoming,
                id: lines[0].id,
                addonIds: [103, 104],
            }),
        ).toEqual([{ ...incoming, addonIds: [103, 104], quantity: 4 }]);
        expect(setLineAddons(lines, lines[2].id, [103])).toEqual([
            lines[0],
            { ...lines[1], quantity: 3 },
        ]);
        expect(setLineQuantity(lines, lines[0].id, 3)[0].id).toBe(lines[0].id);
        expect(removeLine(lines, lines[1].id)).toEqual([lines[0], lines[2]]);
    });
    it('retains excessive direct quantities and blocks only direct shortages', () => {
        expect(
            estimateCart(setLineQuantity(lines, lines[0].id, 2), data)
                .blockingIssues,
        ).toHaveLength(0);
        expect(
            estimateCart(setLineQuantity(lines, lines[0].id, 3), data)
                .blockingIssues,
        ).toHaveLength(1);
        expect(
            estimateCart(setLineQuantity(lines, lines[1].id, 4), data)
                .blockingIssues,
        ).toHaveLength(1);
    });
    it('rejects invalid counts, identities, duplicates and arithmetic overflow', () => {
        for (const quantity of [0, -1, 0.5, NaN, 9007199254741])
            expect(() =>
                setLineQuantity(lines, lines[0].id, quantity),
            ).toThrow();
        expect(() => setLineQuantity(lines, 'missing', 1)).toThrow();
        expect(() => removeLine(lines, 'missing')).toThrow();
        expect(() => normalizeAddonIds([103, 103])).toThrow();
        expect(() =>
            addLine([{ ...lines[0], quantity: 9007199254740 }], lines[0]),
        ).toThrow();
        expect(
            estimateCart([{ ...lines[0], quantity: 9007199254740 }], data)
                .totalMinor,
        ).toBeNull();
    });
    it('fails closed on missing, inactive or disallowed selections', () => {
        for (const line of [
            { ...lines[0], productId: 999 },
            { ...lines[0], productId: 105 },
            { ...lines[0], addonIds: [104] },
        ]) {
            expect(estimateCart([line], data).totalMinor).toBeNull();
            expect(estimateCart([line], data).stockEffects).toEqual([]);
        }
    });
});
