import {
    Dialog,
    DialogContent,
    DialogTitle,
    DialogDescription,
} from '@/components/ui/dialog';
import { usePosDraft } from '@/hooks/use-pos-draft';
import { useCheckoutAttempt } from '@/hooks/use-checkout-attempt';
import { selectedSnapshots, unresolved } from '@/lib/pos/draft-session';
import {
    subscribePosMemory,
    clearPosMemory,
    createAuthScope,
} from '@/lib/pos/auth-scope';
import { requestPosJson } from '@/lib/pos/checkout-requests';
import { emptyPayment, parsePayment } from '@/lib/pos/payment-input';
import type { PosDraftCatalog, CheckoutQuote } from '@/types/pos';
import PaymentPanel from '@/components/pos/payment-panel';
import CheckoutReview from '@/components/pos/checkout-review';
import CheckoutResult from '@/components/pos/checkout-result';
import DraftStatus from '@/components/pos/draft-status';
import { useEffect, useMemo, useRef, useState } from 'react';
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
import { formatPriceMinor } from '@/lib/catalog/form-values';
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
    const scope = useMemo(
        () => ({
            userId: Number(props.scopeKey.split(':')[1]),
            storeId: props.business.storeId,
            storeSlug: props.business.storeSlug,
        }),
        [props.scopeKey, props.business.storeId, props.business.storeSlug],
    );
    const draft = usePosDraft(scope);
    const checkout = useCheckoutAttempt(scope, draft.storage);
    const [paymentForm, setPaymentForm] = useState({ ...emptyPayment });
    const [receivedForTotal, setReceivedForTotal] = useState<number | null>(
        null,
    );
    const [cancelOpen, setCancelOpen] = useState(false);
    const [review, setReview] = useState<CheckoutQuote | null>(null);
    const [verified, setVerified] = useState(true);
    const [online, setOnline] = useState(
        () => typeof navigator === 'undefined' || navigator.onLine !== false,
    );
    const [hydrated, setHydrated] = useState(false);
    const [draftIssues, setDraftIssues] = useState<PosDraftCatalog['issues']>(
        [],
    );
    const restored = useRef('');
    const [authGuard] = useState(createAuthScope);
    const savedContent = useRef('');
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
    const guard = useUnsavedChanges(
        draft.status === 'saving' ||
            draft.status === 'storage-error' ||
            (!!draft.record &&
                !unresolved(draft.record) &&
                !draft.record.terminal &&
                checkout.state?.state !== 'confirmed' &&
                (JSON.stringify(lines) !== JSON.stringify(draft.record.lines) ||
                    JSON.stringify(buffers) !==
                        JSON.stringify(draft.record.quantityBuffers))),
    );
    const estimate = estimateCart(lines, { catalog, inventory });
    const locked =
        !verified ||
        checkout.state?.sessionValid === false ||
        !hydrated ||
        !draft.record ||
        draft.record.owner.tabId !== draft.tabId ||
        unresolved(draft.record) ||
        !!draft.record.terminal ||
        checkout.state?.state === 'unknown' ||
        checkout.state?.state === 'submitting' ||
        checkout.state?.state === 'confirmed';
    const valid =
        !locked &&
        !Object.keys(errors).length &&
        !draftIssues.filter((issue) =>
            lines.some((line) => line.id === issue.field),
        ).length &&
        estimate.totalMinor !== null;
    const parsedPayment = parsePayment(
        paymentForm,
        estimate.totalMinor ?? 0,
        props.enabledPaymentMethods,
    );
    const payment = parsedPayment.payment;
    const quote = usePosQuote({
        scopeKey: props.scopeKey,
        lines,
        valid,
        quoteUrl: quoteRoute(props.business.storeSlug).url,
        payment,
    });
    const receiptMatches =
        paymentForm.method === 'cash' ||
        !paymentForm.receivedConfirmed ||
        receivedForTotal === (quote.quote?.totalMinor ?? estimate.totalMinor);
    useEffect(() => {
        if (!receiptMatches) {
            setPaymentForm((old) => ({ ...old, receivedConfirmed: false }));
            setReview(null);
            setMessage(
                'The total changed. Resolve the difference outside the app, then confirm receipt of the new total.',
            );
        }
    }, [receiptMatches]);
    useEffect(
        () =>
            subscribePosMemory(scope.userId, () => {
                authGuard.invalidate();
                setVerified(false);
                setPaymentForm({ ...emptyPayment });
                setReview(null);
                setLines([]);
                setCatalog(new Map());
                setInventory(new Map());
            }),
        [scope.userId],
    );
    useEffect(() => {
        const network = () => setOnline(navigator.onLine);
        const focus = () => {
            const verification = authGuard.capture();
            if (!authGuard.accepts(verification)) return;
            setVerified(false);
            setPaymentForm({ ...emptyPayment });
            setReview(null);
            void requestPosJson<PosCatalogPage>(
                catalogRoute(scope.storeSlug).url,
                {},
                scope,
                (body) => {
                    if (
                        !body ||
                        typeof body !== 'object' ||
                        !('catalog' in body)
                    )
                        throw new Error('Invalid session response');
                    return body as PosCatalogPage;
                },
            )
                .then(() => {
                    if (authGuard.accepts(verification)) setVerified(true);
                })
                .catch(() => {
                    if (authGuard.accepts(verification))
                        clearPosMemory(scope.userId);
                });
        };
        window.addEventListener('focus', focus);
        window.addEventListener('online', network);
        window.addEventListener('offline', network);
        return () => {
            window.removeEventListener('focus', focus);
            window.removeEventListener('online', network);
            window.removeEventListener('offline', network);
        };
    }, [scope]);
    useEffect(() => {
        const r = draft.record;
        if (!r || !verified) return;
        const identity = r.draftId + ':' + r.owner.epoch;
        if (restored.current !== identity) {
            restored.current = identity;
            setLines(r.lines);
            setBuffers(r.quantityBuffers);
            setPaymentForm({ ...emptyPayment });
            setReview(null);
            const rawErrors: Record<string, string> = {};
            for (const l of r.lines) {
                const raw = r.quantityBuffers[l.id];
                if (
                    raw !== undefined &&
                    (!/^\d+$/.test(raw) || Number(raw) !== l.quantity)
                )
                    rawErrors[l.id] = 'Enter a whole positive quantity.';
            }
            setErrors(rawErrors);
            setCatalog(
                (old) =>
                    new Map([
                        ...old,
                        ...[...r.snapshots.products, ...r.snapshots.addons].map(
                            (x) => [x.id, x] as [number, CatalogListItem],
                        ),
                    ]),
            );
            setInventory(
                (old) =>
                    new Map([
                        ...old,
                        ...r.snapshots.inventory.map(
                            (x) => [x.id, x] as [number, InventoryItem],
                        ),
                    ]),
            );
            setDraftIssues(r.snapshots.issues);
            savedContent.current = JSON.stringify({
                lines: r.lines,
                quantityBuffers: r.quantityBuffers,
                snapshots: r.snapshots,
            });
        }
        if (!r.lines.length || unresolved(r) || r.terminal) {
            setHydrated(true);
            return;
        }
        setHydrated(false);
        let active = true;
        void requestPosJson<PosDraftCatalog>(
            '/' + scope.storeSlug + '/pos/draft-catalog',
            { method: 'POST', body: JSON.stringify({ lines: r.lines }) },
            scope,
            (body) => {
                if (
                    !body ||
                    typeof body !== 'object' ||
                    !('products' in body) ||
                    !('issues' in body)
                )
                    throw new Error('Invalid saved catalog response.');
                return body as PosDraftCatalog;
            },
        )
            .then((next) => {
                if (!active) return;
                setCatalog(
                    (old) =>
                        new Map([
                            ...old,
                            ...[...next.products, ...next.addons].map(
                                (x) => [x.id, x] as [number, CatalogListItem],
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
                setDraftIssues(next.issues);
                if (
                    JSON.stringify(r.snapshots.products) !==
                        JSON.stringify(next.products) ||
                    JSON.stringify(r.snapshots.addons) !==
                        JSON.stringify(next.addons)
                )
                    setMessage(
                        'Saved product prices, recipes or availability changed. Review the current quote.',
                    );
                setHydrated(true);
            })
            .catch((e) => {
                if (active) setMessage(String(e));
            });
        return () => {
            active = false;
        };
    }, [draft.record?.draftId, draft.record?.owner.epoch, scope, verified]);
    useEffect(() => {
        if (!verified || !hydrated || !draftIssues.length) return;
        const controller = new AbortController();
        void requestPosJson<PosDraftCatalog>(
            '/' + scope.storeSlug + '/pos/draft-catalog',
            {
                method: 'POST',
                signal: controller.signal,
                body: JSON.stringify({ lines }),
            },
            scope,
            (body) => body as PosDraftCatalog,
        )
            .then((next) => setDraftIssues(next.issues))
            .catch(() => {});
        return () => controller.abort();
    }, [JSON.stringify(lines), scope, verified, hydrated]);
    const snapshots = selectedSnapshots(lines, {
        products: [...catalog.values()].filter((x) => x.kind !== 'addon'),
        addons: [...catalog.values()].filter((x) => x.kind === 'addon'),
        inventory: [...inventory.values()],
        issues: draftIssues.filter((x) => lines.some((l) => l.id === x.field)),
    });
    const content = JSON.stringify({
        lines,
        quantityBuffers: buffers,
        snapshots,
    });
    const unsaved = content !== savedContent.current;
    useEffect(() => {
        if (locked || !draft.session || draft.status !== 'saved' || !unsaved)
            return;
        const timer = setTimeout(() => {
            void draft
                .session!.saveCart({
                    lines,
                    quantityBuffers: buffers,
                    snapshots,
                })
                .then(() => {
                    savedContent.current = content;
                })
                .catch(() => {});
        }, 100);
        return () => clearTimeout(timer);
    }, [
        content,
        locked,
        draft.status,
        draft.session,
        unsaved,
        lines,
        buffers,
        snapshots,
    ]);
    useEffect(() => {
        setReview(null);
    }, [JSON.stringify(payment), JSON.stringify(lines)]);
    useEffect(() => {
        if (review && quote.status !== 'current') setReview(null);
    }, [quote.status, review]);
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
                scope,
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
        if (locked) return false;
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
                  : 'Quoted · ready for payment'
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
    const resultVisible =
        checkout.state &&
        ['submitting', 'unknown', 'confirmed'].includes(checkout.state.state);
    const footer = (
        <div className="max-h-[65dvh] space-y-3 overflow-y-auto">
            <div className="flex flex-wrap justify-between gap-2 text-xl font-semibold">
                <span>{current ? 'Total' : 'Estimated total'}</span>
                <span className="break-all tabular-nums">
                    {total === null
                        ? 'Unavailable'
                        : `PHP ${formatPriceMinor(total)}`}
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
            <DraftStatus
                status={draft.status}
                onDiscard={
                    draft.discardable ? () => setCancelOpen(true) : undefined
                }
                error={draft.error}
                onTakeover={() => {
                    void draft.session?.takeover().catch(() => {});
                }}
                onReload={() => {
                    void draft.session?.restore().catch(() => {});
                }}
            />
            <PaymentPanel
                form={paymentForm}
                totalMinor={total ?? 0}
                enabledMethods={props.enabledPaymentMethods}
                errors={
                    paymentForm.tenderedText || paymentForm.receivedConfirmed
                        ? parsedPayment.errors
                        : {}
                }
                disabled={locked}
                onChange={(form) => {
                    setReceivedForTotal(
                        form.receivedConfirmed
                            ? (current?.totalMinor ?? estimate.totalMinor)
                            : null,
                    );
                    setPaymentForm(form);
                }}
            />
            {!online && (
                <p role="alert">Offline. Reconnect before recording payment.</p>
            )}
            {draftIssues
                .filter((issue) =>
                    lines.some((line) => line.id === issue.field),
                )
                .map((issue) => (
                    <p
                        role="alert"
                        key={issue.id}
                        className="text-sm text-destructive"
                    >
                        {issue.message}
                    </p>
                ))}
            {checkout.state?.error && (
                <p role="alert">
                    {checkout.state.error.message} Review a new quote before
                    trying again.
                </p>
            )}
            <Button
                disabled={
                    !valid ||
                    !receiptMatches ||
                    !payment ||
                    !current?.payment ||
                    !!current?.blockingIssues.length ||
                    !online ||
                    draft.status !== 'saved' ||
                    unsaved ||
                    !checkout.session
                }
                className="min-h-11 w-full"
                onClick={() => {
                    if (current) setReview(current);
                }}
            >
                Review payment
            </Button>
            <Button
                disabled={!lines.length || locked}
                variant="outline"
                className="min-h-11 w-full"
                onClick={() => setCancelOpen(true)}
            >
                Cancel cart
            </Button>
        </div>
    );
    if (!verified)
        return (
            <PosLayout
                header="STOCKRAGE · Session verification"
                summary="Session locked"
                picker={
                    <p role="alert">
                        Verify your session or sign in as the original owner.
                        Saved carts and unresolved payment recordings are
                        retained.
                    </p>
                }
                cart={null}
                footer={
                    <Button
                        className="min-h-11"
                        onClick={() => window.location.reload()}
                    >
                        Reload session
                    </Button>
                }
            />
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
                                Draft saves stay on this browser for this owner
                                and store.
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
                        {total === null ? '—' : formatPriceMinor(total)} ·{' '}
                        {status}
                    </span>
                }
                footer={
                    resultVisible && checkout.state ? (
                        <CheckoutResult
                            state={checkout.state}
                            onCheck={() => {
                                void checkout.session
                                    ?.checkAgain()
                                    .then(() => draft.session?.restore());
                            }}
                            onRetry={() => {
                                void checkout.session
                                    ?.retryOriginal()
                                    .then(() => {
                                        checkout.session?.recover();
                                        void draft.session?.restore();
                                    });
                            }}
                            onNewSale={() => {
                                const r = draft.record;
                                if (!r || !draft.storage) return;
                                void draft.storage
                                    .read(scope)
                                    .then((latest) => {
                                        if (!latest)
                                            throw new Error(
                                                'Reload saved confirmation.',
                                            );
                                        return draft.storage!.newSale(
                                            scope,
                                            latest.revision,
                                            draft.tabId,
                                        );
                                    })
                                    .then(async () => {
                                        await checkout.session?.restore();
                                        await draft.session?.restore();
                                        setRetry((x) => x + 1);
                                    })
                                    .catch((e) => setMessage(String(e)));
                            }}
                        />
                    ) : (
                        footer
                    )
                }
                picker={
                    <fieldset disabled={locked} className="space-y-5">
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
                                            {formatPriceMinor(
                                                product.priceMinor,
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
                                these changes before recording payment.
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
                    </fieldset>
                }
                cart={
                    <fieldset disabled={locked}>
                        <CartLines
                            lines={lines}
                            catalog={catalog}
                            quote={current}
                            buffers={buffers}
                            errors={errors}
                            quantity={(lineId, value) => {
                                setBuffers((old) => ({
                                    ...old,
                                    [lineId]: value,
                                }));
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
                                if (errors[line.id]) {
                                    setMessage(
                                        'Correct this line’s quantity before editing add-ons.',
                                    );
                                    return;
                                }
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
                    </fieldset>
                }
            />
            <Dialog open={cancelOpen} onOpenChange={setCancelOpen}>
                <DialogContent className="[&>button]:min-h-11 [&>button]:min-w-11">
                    <DialogTitle>Cancel this cart?</DialogTitle>
                    <DialogDescription>
                        The saved editable cart will be discarded. No payment
                        recording will be submitted.
                    </DialogDescription>
                    <Button
                        className="min-h-11"
                        onClick={() => setCancelOpen(false)}
                    >
                        Keep cart
                    </Button>
                    <Button
                        className="min-h-11"
                        variant="outline"
                        disabled={locked && !draft.discardable}
                        onClick={() => {
                            const r = draft.discardable ?? draft.record;
                            if (!r || !draft.storage) return;
                            void draft.storage
                                .discard(scope, r.owner, r.revision)
                                .then(() =>
                                    draft.storage!.create(scope, draft.tabId),
                                )
                                .then(() => draft.session?.restore())
                                .then(() => {
                                    setCancelOpen(false);
                                    setPaymentForm({ ...emptyPayment });
                                    setMessage('Cart canceled.');
                                })
                                .catch((e) => setMessage(String(e)));
                        }}
                    >
                        Discard cart
                    </Button>
                </DialogContent>
            </Dialog>
            {review && (
                <CheckoutReview
                    quote={review}
                    disabled={
                        !valid ||
                        unsaved ||
                        draft.status !== 'saved' ||
                        quote.status !== 'current' ||
                        quote.quote?.id !== review.id ||
                        !online
                    }
                    busy={checkout.state?.busy ?? false}
                    onBack={() => setReview(null)}
                    onConfirm={() => {
                        const r = draft.record;
                        if (
                            !r ||
                            !review.payment ||
                            !checkout.session ||
                            !receiptMatches
                        )
                            return;
                        setReview(null);
                        void checkout.session
                            .submit(
                                {
                                    idempotencyKey: crypto.randomUUID(),
                                    quoteId: review.id,
                                    quoteRevision: review.revision,
                                    lines: structuredClone(lines),
                                    payment: review.payment,
                                    acknowledgedWarningIds: review.warnings.map(
                                        (x) => x.id,
                                    ),
                                },
                                r.owner,
                                r.revision,
                            )
                            .then(() => {
                                void draft.session?.restore();
                                if (
                                    checkout.session?.snapshot().state ===
                                    'rejected'
                                )
                                    quote.retry();
                                else checkout.session?.recover();
                            });
                    }}
                />
            )}
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
