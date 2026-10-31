import type {
    CartLine,
    CatalogItem,
    InventoryItem,
    StockEffect,
    ContractIssue,
} from '@/types';
export type CartData = {
    catalog: ReadonlyMap<number, CatalogItem>;
    inventory: ReadonlyMap<number, InventoryItem>;
};
export type CartEstimate = {
    subtotalMinor: number | null;
    totalMinor: number | null;
    stockEffects: StockEffect[];
    blockingIssues: ContractIssue[];
    warnings: ContractIssue[];
};
export class CartInputError extends Error {}
const MAX = 9007199254740991n;
export const MAX_COUNT = 9007199254740;
const uuid = /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i;
function check(value: bigint): bigint {
    if (value > MAX || value < -MAX)
        throw new CartInputError('numeric_range_exceeded');
    return value;
}
function count(value: number): number {
    if (!Number.isSafeInteger(value) || value < 1 || value > MAX_COUNT)
        throw new CartInputError(
            'Enter a whole quantity from 1 to 9007199254740.',
        );
    return value;
}
function id(value: number) {
    if (!Number.isSafeInteger(value) || value < 1)
        throw new CartInputError('Invalid item ID.');
}
export function normalizeAddonIds(ids: readonly number[]): number[] {
    ids.forEach(id);
    if (new Set(ids).size !== ids.length)
        throw new CartInputError('An add-on cannot repeat.');
    return [...ids].sort((a, b) => a - b);
}
export function lineKey(
    productId: number,
    addonIds: readonly number[],
): string {
    id(productId);
    return `${productId}:${normalizeAddonIds(addonIds).join(',')}`;
}
function validate(line: CartLine) {
    if (!uuid.test(line.id)) throw new CartInputError('Invalid line ID.');
    count(line.quantity);
    lineKey(line.productId, line.addonIds);
}
function find(lines: readonly CartLine[], lineId: string): CartLine {
    const line = lines.find((x) => x.id === lineId);
    if (!line) throw new CartInputError('Unknown cart line.');
    return line;
}
export function addLine(
    lines: readonly CartLine[],
    incoming: CartLine,
): CartLine[] {
    validate(incoming);
    const target = lines.find(
        (x) =>
            lineKey(x.productId, x.addonIds) ===
            lineKey(incoming.productId, incoming.addonIds),
    );
    if (target)
        return setLineQuantity(
            lines,
            target.id,
            count(target.quantity + incoming.quantity),
        );
    if (lines.some((x) => x.id === incoming.id))
        throw new CartInputError('Duplicate line ID.');
    return [
        ...lines,
        { ...incoming, addonIds: normalizeAddonIds(incoming.addonIds) },
    ];
}
export function setLineQuantity(
    lines: readonly CartLine[],
    lineId: string,
    quantity: number,
): CartLine[] {
    find(lines, lineId);
    count(quantity);
    return lines.map((x) => (x.id === lineId ? { ...x, quantity } : x));
}
export function removeLine(
    lines: readonly CartLine[],
    lineId: string,
): CartLine[] {
    find(lines, lineId);
    return lines.filter((x) => x.id !== lineId);
}
export function setLineAddons(
    lines: readonly CartLine[],
    lineId: string,
    addonIds: number[],
): CartLine[] {
    const source = find(lines, lineId),
        normalized = normalizeAddonIds(addonIds);
    const target = lines.find(
        (x) =>
            x.id !== lineId &&
            lineKey(x.productId, x.addonIds) ===
                lineKey(source.productId, normalized),
    );
    if (target)
        return setLineQuantity(
            removeLine(lines, lineId),
            target.id,
            count(target.quantity + source.quantity),
        );
    return lines.map((x) =>
        x.id === lineId ? { ...x, addonIds: normalized } : x,
    );
}
export function parseStock(value: string, unit: InventoryItem['unit']): bigint {
    if (!/^-?\d+\.\d{3}$/.test(value))
        throw new CartInputError('Invalid stock quantity.');
    const scaled = check(BigInt(value.replace('.', '')));
    if (unit === 'each' && scaled % 1000n !== 0n)
        throw new CartInputError('Counted stock must be whole.');
    return scaled;
}
export function formatStock(value: bigint): string {
    check(value);
    const abs = value < 0n ? -value : value;
    return `${value < 0n ? '-' : ''}${abs / 1000n}.${String(abs % 1000n).padStart(3, '0')}`;
}
export function estimateCart(
    lines: readonly CartLine[],
    data: CartData,
): CartEstimate {
    const issue = (message: string): ContractIssue => ({
        id: 'configuration',
        code: 'invalid_configuration',
        message,
        field: null,
        inventoryItemId: null,
    });
    try {
        let total = 0n;
        const demands = new Map<number, bigint>();
        const seen = new Set<string>();
        for (const line of lines) {
            validate(line);
            if (seen.has(line.id))
                throw new CartInputError('Duplicate line ID.');
            seen.add(line.id);
            const product = data.catalog.get(line.productId);
            if (!product?.active || product.kind === 'addon')
                throw new CartInputError('Select an active product.');
            const selected = [
                product,
                ...normalizeAddonIds(line.addonIds).map((addonId) => {
                    const addon = data.catalog.get(addonId);
                    if (
                        !addon?.active ||
                        addon.kind !== 'addon' ||
                        !product.allowedAddonIds.includes(addonId)
                    )
                        throw new CartInputError(
                            'Select allowed active add-ons.',
                        );
                    return addon;
                }),
            ];
            let price = 0n;
            for (const item of selected) {
                if (
                    !Number.isSafeInteger(item.priceMinor) ||
                    item.priceMinor < 0
                )
                    throw new CartInputError('Invalid price.');
                price = check(price + BigInt(item.priceMinor));
                const parts =
                    'stockItemId' in item
                        ? [
                              {
                                  inventoryItemId: item.stockItemId,
                                  quantity: '1.000',
                                  unit: 'each' as const,
                              },
                          ]
                        : item.components;
                if (!parts.length)
                    throw new CartInputError('Recipe has no components.');
                const partIds = new Set<number>();
                for (const part of parts) {
                    const stock = data.inventory.get(part.inventoryItemId);
                    if (
                        !stock?.active ||
                        stock.unit !== part.unit ||
                        partIds.has(part.inventoryItemId) ||
                        (!('stockItemId' in item) &&
                            !['ingredient', 'supply'].includes(stock.kind))
                    )
                        throw new CartInputError(
                            'Repair missing or inactive stock configuration.',
                        );
                    partIds.add(part.inventoryItemId);
                    const amount = parseStock(part.quantity, part.unit);
                    if (amount <= 0n)
                        throw new CartInputError(
                            'Recipe quantities must be positive.',
                        );
                    demands.set(
                        stock.id,
                        check(
                            (demands.get(stock.id) ?? 0n) +
                                check(amount * BigInt(line.quantity)),
                        ),
                    );
                }
            }
            total = check(total + check(price * BigInt(line.quantity)));
        }
        const blockingIssues: ContractIssue[] = [],
            warnings: ContractIssue[] = [];
        const stockEffects = [...demands]
            .sort(([a], [b]) => a - b)
            .map(([stockId, demand]): StockEffect => {
                const stock = data.inventory.get(stockId)!;
                const before = parseStock(stock.balance, stock.unit),
                    after = check(before - demand);
                const effect = {
                    inventoryItemId: stockId,
                    inventoryItemName: stock.name,
                    unit: stock.unit,
                    before: formatStock(before),
                    demand: formatStock(demand),
                    after: formatStock(after),
                    policy: stock.shortagePolicy,
                };
                if (after < 0n)
                    (stock.shortagePolicy === 'block'
                        ? blockingIssues
                        : warnings
                    ).push({
                        id: `${stock.shortagePolicy === 'block' ? 'direct' : 'recipe'}-stock-${stockId}`,
                        code:
                            stock.shortagePolicy === 'block'
                                ? 'direct_stock_shortage'
                                : 'recipe_stock_warning',
                        message: `${stock.name}: available ${effect.before}, requested ${effect.demand}, projected ${effect.after} ${stock.unit}`,
                        field: null,
                        inventoryItemId: stockId,
                    });
                return effect;
            });
        return {
            subtotalMinor: Number(total),
            totalMinor: Number(total),
            stockEffects,
            blockingIssues,
            warnings,
        };
    } catch (error) {
        return {
            subtotalMinor: null,
            totalMinor: null,
            stockEffects: [],
            blockingIssues: [
                issue(error instanceof Error ? error.message : 'Invalid cart.'),
            ],
            warnings: [],
        };
    }
}
