import { useEffect, useRef, useState } from 'react';
import { Head, Link, usePage } from '@inertiajs/react';
import type {
    CartLine,
    CatalogListItem,
    InventoryItem,
    PosCatalogPage,
    PosPageProps,
} from '@/types';
import PosLayout from '@/layouts/pos-layout';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { TeamSwitcher } from '@/components/team-switcher';
import AddonDialog from '@/components/pos/addon-dialog';
import CartLines from '@/components/pos/cart-lines';
import { useUnsavedChanges } from '@/hooks/use-unsaved-changes';
import { usePosQuote } from '@/hooks/use-pos-quote';
import {
    addLine,
    setLineQuantity,
    setLineAddons,
    removeLine,
    estimateCart,
} from '@/lib/pos/cart';
import { createRequestGate, requestJson } from '@/lib/pos/requests';
import { dashboard } from '@/routes';
import { catalog as catalogRoute } from '@/routes/pos';
import { store as quoteRoute } from '@/routes/pos/quotes';

export default function Pos(props: PosPageProps) {
    const { auth } = usePage().props;
    return (
        <PosCart
            key={`${props.business.storeId}:${auth.user.id}`}
            {...props}
            scopeKey={`${props.business.storeId}:${auth.user.id}`}
        />
    );
}
function PosCart(props: PosPageProps & { scopeKey: string }) {
    const [lines, setLines] = useState<CartLine[]>([]);
    const [page, setPage] = useState<PosCatalogPage>(props);
    const [catalog, setCatalog] = useState(
        () =>
            new Map(
                [...props.catalog.data, ...props.addons].map((x) => [x.id, x]),
            ),
    );
    const [inventory, setInventory] = useState(
        () => new Map(props.inventory.map((x) => [x.id, x])),
    );
    const [query, setQuery] = useState(props.filters.query),
        [categoryId, setCategoryId] = useState(props.filters.categoryId),
        [pageNumber, setPageNumber] = useState(1),
        [retry, setRetry] = useState(0);
    const [loading, setLoading] = useState(false),
        [catalogError, setCatalogError] = useState('');
    const [buffers, setBuffers] = useState<Record<string, string>>({}),
        [errors, setErrors] = useState<Record<string, string>>({});
    const [message, setMessage] = useState('');
    const [dialog, setDialog] = useState<{
        product: CatalogListItem;
        line: CartLine | null;
    } | null>(null);
    const opener = useRef<HTMLElement | null>(null);
    const [gate] = useState(createRequestGate);
    const guard = useUnsavedChanges(lines.length > 0);
    const estimate = estimateCart(lines, { catalog, inventory });
    const valid = !Object.keys(errors).length && estimate.totalMinor !== null;
    const quote = usePosQuote({
        scopeKey: props.scopeKey,
        lines,
        valid,
        quoteUrl: quoteRoute(props.business.storeSlug).url,
    });
    useEffect(() => {
        const key = JSON.stringify([query, categoryId, pageNumber, retry]),
            generation = gate.next(props.scopeKey, key),
            controller = new AbortController();
        const timer = setTimeout(() => {
            setLoading(true);
            setCatalogError('');
            void requestJson<PosCatalogPage>(
                catalogRoute(props.business.storeSlug, {
                    query: {
                        query,
                        categoryId: categoryId ?? '',
                        page: pageNumber,
                    },
                }).url,
                { signal: controller.signal },
            )
                .then((next) => {
                    if (!gate.accepts(generation, props.scopeKey, key)) return;
                    if (
                        !next ||
                        !Array.isArray(next.catalog?.data) ||
                        !Array.isArray(next.addons) ||
                        !Array.isArray(next.inventory)
                    )
                        throw new Error('Invalid catalog response.');
                    setPage(next);
                    setCatalog(
                        (old) =>
                            new Map([
                                ...old,
                                ...[...next.catalog.data, ...next.addons].map(
                                    (x) =>
                                        [x.id, x] as [number, CatalogListItem],
                                ),
                            ]),
                    );
                    setInventory(
                        (old) =>
                            new Map([
                                ...old,
                                ...next.inventory.map(
                                    (x) => [x.id, x] as [number, InventoryItem],
                                ),
                            ]),
                    );
                })
                .catch((error) => {
                    if (
                        gate.accepts(generation, props.scopeKey, key) &&
                        error.name !== 'AbortError'
                    )
                        setCatalogError(error.message);
                })
                .finally(() => {
                    if (gate.accepts(generation, props.scopeKey, key))
                        setLoading(false);
                });
        }, 300);
        return () => {
            clearTimeout(timer);
            controller.abort();
            gate.invalidate();
        };
    }, [
        query,
        categoryId,
        pageNumber,
        retry,
        gate,
        props.scopeKey,
        props.business.storeSlug,
    ]);
    const change = (operation: () => CartLine[]) => {
        try {
            const next = operation();
            setLines(next);
            setBuffers((old) =>
                Object.fromEntries(
                    Object.entries(old)
                        .filter(([id]) => next.some((x) => x.id === id))
                        .map(([id, value]) => [
                            id,
                            errors[id]
                                ? value
                                : String(
                                      next.find((x) => x.id === id)!.quantity,
                                  ),
                        ]),
                ),
            );
            setErrors((old) =>
                Object.fromEntries(
                    Object.entries(old).filter(([id]) =>
                        next.some((x) => x.id === id),
                    ),
                ),
            );
            setMessage('');
            return true;
        } catch (error) {
            setMessage(
                error instanceof Error ? error.message : 'Invalid cart.',
            );
            return false;
        }
    };
    const closeDialog = () => {
        setDialog(null);
        requestAnimationFrame(() => opener.current?.focus());
    };
    const openDialog = (product: CatalogListItem, line: CartLine | null) => {
        opener.current = document.activeElement as HTMLElement;
        setDialog({ product, line });
    };
    const add = (product: CatalogListItem, addonIds: number[]) =>
        change(() =>
            addLine(lines, {
                id: crypto.randomUUID(),
                productId: product.id,
                quantity: 1,
                addonIds,
            }),
        );
    const current = quote.status === 'current' ? quote.quote : null;
    const displayed = Object.keys(errors).length
        ? { ...estimate, totalMinor: null, stockEffects: [] }
        : (current ?? estimate);
    const total = displayed.totalMinor;
    const status =
        quote.status === 'current'
            ? current!.blockingIssues.length
                ? 'Blocked · correct direct stock quantities'
                : current!.warnings.length
                  ? 'Quoted · ingredient warnings'
                  : 'Quoted · ready for preparation'
            : quote.status === 'empty'
              ? 'Empty cart'
              : quote.status === 'invalid'
                ? 'Invalid cart · correct fields'
                : quote.status === 'error'
                  ? 'Quote failed · estimates only'
                  : quote.status === 'stale'
                    ? 'Stale quote · estimates only'
                    : 'Checking · estimates only';
    const changed =
        current &&
        (current.totalMinor !== estimate.totalMinor ||
            JSON.stringify(current.stockEffects) !==
                JSON.stringify(estimate.stockEffects));
    const footer = (
        <div className="space-y-3">
            <div className="flex flex-wrap justify-between gap-2 text-xl font-semibold">
                <span>{current ? 'Total' : 'Estimated total'}</span>
                <span className="break-all tabular-nums">
                    {total === null
                        ? 'Unavailable'
                        : `PHP ${(total / 100).toFixed(2)}`}
                </span>
            </div>
            <p role="status" aria-live="polite" className="text-sm">
                {status}
            </p>
            {quote.error && (
                <div role="alert" className="text-sm text-destructive">
                    <p>{quote.error.message}</p>
                    {Object.entries(quote.error.fieldErrors).map(
                        ([field, messages]) => (
                            <p key={field}>
                                {field}: {messages.join(' ')}
                            </p>
                        ),
                    )}
                </div>
            )}
            {(quote.status === 'error' || quote.status === 'stale') && (
                <Button
                    variant="outline"
                    className="min-h-11 w-full"
                    onClick={quote.retry}
                >
                    Retry quote
                </Button>
            )}
            <Button disabled className="min-h-11 w-full">
                Payment unavailable
            </Button>
            <p className="text-sm text-muted-foreground">
                Payment recording will be available in the next checkout stage.
            </p>
            <Button
                disabled={!lines.length}
                variant="outline"
                className="min-h-11 w-full"
                onClick={() => {
                    guard(() => {
                        setLines([]);
                        setBuffers({});
                        setErrors({});
                        setDialog(null);
                        setCatalog(
                            new Map(
                                [...page.catalog.data, ...page.addons].map(
                                    (x) => [x.id, x],
                                ),
                            ),
                        );
                        setInventory(
                            new Map(page.inventory.map((x) => [x.id, x])),
                        );
                        setMessage('Cart canceled.');
                    });
                }}
            >
                Cancel cart
            </Button>
        </div>
    );
    return (
        <>
            <Head title="POS" />
            <PosLayout
                header={
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h1 className="font-semibold">
                                STOCKRAGE · Prepare order
                            </h1>
                            <p className="text-sm text-muted-foreground">
                                Cart is not saved. Leaving or refreshing may
                                discard it.
                            </p>
                        </div>
                        <div className="flex items-center gap-3">
                            <TeamSwitcher />
                            <Link
                                className="inline-flex min-h-11 items-center underline"
                                href={dashboard(props.business.storeSlug)}
                            >
                                Management
                            </Link>
                        </div>
                    </div>
                }
                summary={
                    <span className="flex flex-wrap gap-2">
                        Cart ({lines.length}) · PHP{' '}
                        {total === null ? '—' : (total / 100).toFixed(2)} ·{' '}
                        {status}
                    </span>
                }
                footer={footer}
                picker={
                    <section className="space-y-5">
                        <div>
                            <h2 className="text-2xl font-semibold">Products</h2>
                            <p className="text-sm text-muted-foreground">
                                Choose retail and food. Stock is checked for the
                                complete cart.
                            </p>
                        </div>
                        <div className="flex flex-wrap gap-3">
                            <div className="min-w-0 flex-1">
                                <label className="text-sm" htmlFor="pos-search">
                                    Search name or SKU
                                </label>
                                <Input
                                    id="pos-search"
                                    className="min-h-11"
                                    maxLength={255}
                                    value={query}
                                    onChange={(e) => {
                                        setQuery(e.target.value);
                                        setPageNumber(1);
                                    }}
                                />
                            </div>
                            <div>
                                <label
                                    className="block text-sm"
                                    htmlFor="pos-category"
                                >
                                    Category
                                </label>
                                <select
                                    id="pos-category"
                                    className="min-h-11 max-w-56 rounded-md border bg-background px-3"
                                    value={categoryId ?? ''}
                                    onChange={(e) => {
                                        setCategoryId(
                                            e.target.value
                                                ? Number(e.target.value)
                                                : null,
                                        );
                                        setPageNumber(1);
                                    }}
                                >
                                    <option value="">All categories</option>
                                    {props.categories.map((x) => (
                                        <option key={x.id} value={x.id}>
                                            {x.name}
                                        </option>
                                    ))}
                                </select>
                            </div>
                        </div>
                        {catalogError && (
                            <div role="alert">
                                <p>{catalogError}</p>
                                <Button
                                    className="min-h-11"
                                    onClick={() => setRetry((x) => x + 1)}
                                >
                                    Retry catalog
                                </Button>
                            </div>
                        )}
                        <p
                            role="status"
                            className="text-sm text-muted-foreground"
                        >
                            {loading
                                ? 'Loading products…'
                                : `${page.catalog.total} products`}
                        </p>
                        <div className="grid grid-cols-2 gap-3 xl:grid-cols-3">
                            {page.catalog.data.map((product) => {
                                const blocked =
                                    product.availability.issues.some(
                                        (x) =>
                                            x.code === 'invalid_configuration',
                                    );
                                return (
                                    <Button
                                        key={product.id}
                                        variant="outline"
                                        disabled={
                                            blocked || loading || !!catalogError
                                        }
                                        className="h-auto min-h-28 min-w-0 flex-col items-start gap-2 p-4 text-left whitespace-normal"
                                        onClick={() =>
                                            product.allowedAddonIds.length
                                                ? openDialog(product, null)
                                                : add(product, [])
                                        }
                                    >
                                        <span className="w-full font-semibold break-words">
                                            {product.name}
                                        </span>
                                        <span className="text-sm tabular-nums">
                                            PHP{' '}
                                            {(product.priceMinor / 100).toFixed(
                                                2,
                                            )}
                                        </span>
                                        <span className="text-xs text-muted-foreground">
                                            {blocked
                                                ? 'Repair configuration'
                                                : product.kind === 'retail'
                                                  ? `Available ${product.availability.stock?.balance ?? 'unknown'}`
                                                  : 'Made to order · recipe stock'}
                                            {product.allowedAddonIds.length
                                                ? ' · Add-ons'
                                                : ''}
                                        </span>
                                    </Button>
                                );
                            })}
                        </div>
                        {!loading && !page.catalog.data.length && (
                            <p>
                                {page.hasProducts
                                    ? 'No products match these filters.'
                                    : 'No active products. Create products in Catalog.'}
                            </p>
                        )}
                        <div className="flex flex-wrap items-center gap-3">
                            <Button
                                variant="outline"
                                className="min-h-11"
                                disabled={loading || pageNumber <= 1}
                                onClick={() => setPageNumber((x) => x - 1)}
                            >
                                Previous
                            </Button>
                            <span>
                                Page {page.catalog.currentPage} of{' '}
                                {page.catalog.lastPage}
                            </span>
                            <Button
                                variant="outline"
                                className="min-h-11"
                                disabled={
                                    loading ||
                                    pageNumber >= page.catalog.lastPage
                                }
                                onClick={() => setPageNumber((x) => x + 1)}
                            >
                                Next
                            </Button>
                        </div>
                        {changed && (
                            <p role="status" className="rounded border p-3">
                                Current server prices or recipe stock differ
                                from the local estimate. The quoted total and
                                projections below use current store data. Review
                                these changes before the next checkout stage.
                            </p>
                        )}
                        {!!lines.length && (
                            <section className="space-y-3 border-t pt-4">
                                <h3 className="font-semibold">
                                    {current ? 'Quoted' : 'Estimated'} stock
                                    projection
                                </h3>
                                {displayed.stockEffects.map((effect) => (
                                    <p
                                        key={effect.inventoryItemId}
                                        className="text-sm break-words"
                                    >
                                        {effect.inventoryItemName}: available{' '}
                                        {effect.before} − demand {effect.demand}{' '}
                                        = {effect.after} {effect.unit}
                                    </p>
                                ))}
                                {[
                                    ...displayed.blockingIssues,
                                    ...displayed.warnings,
                                ].map((issue) => (
                                    <p
                                        key={issue.id}
                                        className="text-sm break-words"
                                    >
                                        {displayed.blockingIssues.includes(
                                            issue,
                                        )
                                            ? 'Blocked: '
                                            : 'Warning: '}
                                        {issue.message}
                                    </p>
                                ))}
                            </section>
                        )}
                        <p aria-live="polite" role="status">
                            {message}
                        </p>
                    </section>
                }
                cart={
                    <CartLines
                        lines={lines}
                        catalog={catalog}
                        quote={current}
                        buffers={buffers}
                        errors={errors}
                        quantity={(lineId, value) => {
                            setBuffers((old) => ({ ...old, [lineId]: value }));
                            try {
                                if (!/^\d+$/.test(value))
                                    throw new Error(
                                        'Enter a whole positive quantity.',
                                    );
                                const next = setLineQuantity(
                                    lines,
                                    lineId,
                                    Number(value),
                                );
                                setLines(next);
                                setErrors((old) => {
                                    const copy = { ...old };
                                    delete copy[lineId];
                                    return copy;
                                });
                            } catch (error) {
                                setErrors((old) => ({
                                    ...old,
                                    [lineId]:
                                        error instanceof Error
                                            ? error.message
                                            : 'Invalid quantity.',
                                }));
                            }
                        }}
                        edit={(line) => {
                            const product = catalog.get(line.productId);
                            if (product) openDialog(product, line);
                        }}
                        remove={(lineId) => {
                            setLines(removeLine(lines, lineId));
                            setErrors((old) => {
                                const next = { ...old };
                                delete next[lineId];
                                return next;
                            });
                            setBuffers((old) => {
                                const next = { ...old };
                                delete next[lineId];
                                return next;
                            });
                        }}
                    />
                }
            />
            {dialog && (
                <AddonDialog
                    key={dialog.line?.id ?? dialog.product.id}
                    product={dialog.product}
                    addons={[...catalog.values()].filter(
                        (x) => x.kind === 'addon',
                    )}
                    selected={dialog.line?.addonIds ?? []}
                    close={closeDialog}
                    apply={(ids) => {
                        if (dialog.line) {
                            const line = dialog.line;
                            if (
                                change(() => setLineAddons(lines, line.id, ids))
                            ) {
                                setMessage(
                                    'Add-ons updated. Matching configurations were merged.',
                                );
                                closeDialog();
                            }
                        } else if (add(dialog.product, ids)) closeDialog();
                    }}
                />
            )}
            {guard.dialog}
        </>
    );
}
