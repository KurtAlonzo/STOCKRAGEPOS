import { managementFilters } from '@/lib/management-filters';
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { ItemDialog } from '@/components/inventory/item-dialog';
import { StockReceiptDialog } from '@/components/inventory/stock-receipt-dialog';
import { MovementPanel } from '@/components/inventory/movement-panel';
import { index } from '@/routes/inventory';
import type { InventoryIndexProps, InventoryItem } from '@/types/inventory';

export const stockLabels = {
    negative: 'Negative',
    zero: 'Out of stock',
    low: 'Low stock',
    positive: 'In stock',
};
export default function Inventory(props: InventoryIndexProps) {
    const { business, inventory, filters } = props;
    const [operation, setOperation] = useState<{
        item: InventoryItem;
        adjustment: boolean;
    } | null>(null);
    const [editing, setEditing] = useState<InventoryItem | null | undefined>(
        undefined,
    );
    const [query, setQuery] = useState(filters.query);
    const url = index(business.storeSlug).url;
    function filter(key: string, value: string) {
        router.get(
            url,
            {
                ...managementFilters(filters),
                [key]: value || undefined,
                page: 1,
            },
            { preserveState: true, replace: true },
        );
    }
    return (
        <div className="space-y-6 p-4 md:p-6">
            <Head title="Inventory" />
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 className="text-2xl font-semibold">Inventory</h1>
                    <p className="text-sm text-muted-foreground">
                        Stock balances, ingredients and supplies for{' '}
                        {business.storeSlug}.
                    </p>
                </div>
                <Button onClick={() => setEditing(null)}>
                    Add ingredient or supply
                </Button>
            </div>
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    filter('query', query);
                }}
                className="flex flex-wrap gap-2"
            >
                <Input
                    className="w-full sm:w-64"
                    aria-label="Search inventory"
                    placeholder="Search inventory"
                    value={query}
                    onChange={(e) => setQuery(e.target.value)}
                />
                <Button variant="outline">Search</Button>
                <select
                    aria-label="Inventory type"
                    className="rounded-md border bg-background p-2"
                    value={filters.kind ?? ''}
                    onChange={(e) => filter('kind', e.target.value)}
                >
                    <option value="">All types</option>
                    {['retail', 'ingredient', 'supply', 'addon'].map((kind) => (
                        <option key={kind}>{kind}</option>
                    ))}
                </select>
                <select
                    aria-label="Stock status"
                    className="rounded-md border bg-background p-2"
                    value={filters.stockState ?? ''}
                    onChange={(e) => filter('stockState', e.target.value)}
                >
                    <option value="">All stock states</option>
                    {Object.entries(stockLabels).map(([key, label]) => (
                        <option value={key} key={key}>
                            {label}
                        </option>
                    ))}
                </select>
                <select
                    aria-label="Active state"
                    className="rounded-md border bg-background p-2"
                    value={
                        filters.active === null
                            ? ''
                            : filters.active
                              ? '1'
                              : '0'
                    }
                    onChange={(e) => filter('active', e.target.value)}
                >
                    <option value="">All items</option>
                    <option value="1">Active</option>
                    <option value="0">Inactive</option>
                </select>
            </form>
            {!inventory.data.length && (
                <p className="rounded-md border p-8 text-muted-foreground">
                    {filters.query ||
                    filters.kind ||
                    filters.stockState ||
                    filters.active !== null
                        ? 'No items match these filters.'
                        : 'No inventory yet. Add an ingredient or supply, or create a retail product in Catalog.'}
                </p>
            )}
            <div className="grid gap-3 lg:grid-cols-2">
                {inventory.data.map((item) => (
                    <article
                        key={item.id}
                        className="min-w-0 space-y-3 rounded-md border p-4"
                    >
                        <div className="flex flex-wrap justify-between gap-2">
                            <h2 className="font-semibold break-words">
                                {item.name}
                            </h2>
                            <span
                                className={
                                    item.stockState === 'negative'
                                        ? 'font-medium text-destructive'
                                        : 'text-sm'
                                }
                            >
                                {stockLabels[item.stockState]}
                                {!item.active && ' · Inactive'}
                            </span>
                        </div>
                        <p className="text-sm text-muted-foreground">
                            {item.kind} · {item.balance} {item.unit} · Threshold{' '}
                            {item.lowStockThreshold ?? 'not set'}
                        </p>
                        <div className="flex flex-wrap gap-2">
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() => setEditing(item)}
                            >
                                Edit
                            </Button>
                            <Button
                                size="sm"
                                onClick={() =>
                                    setOperation({ item, adjustment: false })
                                }
                            >
                                Receive
                            </Button>
                            <Button
                                size="sm"
                                variant="outline"
                                onClick={() =>
                                    setOperation({ item, adjustment: true })
                                }
                            >
                                Correct
                            </Button>
                            <Button variant="outline" size="sm" asChild>
                                <Link
                                    href={index(business.storeSlug, {
                                        query: {
                                            ...managementFilters(filters),
                                            itemId: item.id,
                                        },
                                    })}
                                >
                                    View movements
                                </Link>
                            </Button>
                        </div>
                    </article>
                ))}
            </div>
            <div className="flex items-center gap-3">
                <Button
                    variant="outline"
                    disabled={inventory.currentPage <= 1}
                    onClick={() =>
                        router.get(url, {
                            ...managementFilters(filters),
                            page: inventory.currentPage - 1,
                        })
                    }
                >
                    Previous
                </Button>
                <span className="text-sm">
                    Page {inventory.currentPage} of {inventory.lastPage} ·{' '}
                    {inventory.total} items
                </span>
                <Button
                    variant="outline"
                    disabled={inventory.currentPage >= inventory.lastPage}
                    onClick={() =>
                        router.get(url, {
                            ...managementFilters(filters),
                            page: inventory.currentPage + 1,
                        })
                    }
                >
                    Next
                </Button>
            </div>
            {props.selectedItem && (
                <div className="flex flex-wrap gap-2">
                    <Button
                        onClick={() =>
                            setOperation({
                                item: props.selectedItem!,
                                adjustment: false,
                            })
                        }
                    >
                        Receive {props.selectedItem.name}
                    </Button>
                    <Button
                        variant="outline"
                        onClick={() =>
                            setOperation({
                                item: props.selectedItem!,
                                adjustment: true,
                            })
                        }
                    >
                        Correct selected stock
                    </Button>
                </div>
            )}
            <MovementPanel {...props} />
            {operation && (
                <StockReceiptDialog
                    slug={business.storeSlug}
                    item={operation.item}
                    adjustment={operation.adjustment}
                    close={() => setOperation(null)}
                />
            )}
            {editing !== undefined && (
                <ItemDialog
                    key={editing?.id ?? 'new'}
                    slug={business.storeSlug}
                    item={editing}
                    close={() => setEditing(undefined)}
                />
            )}
        </div>
    );
}
