import { Head, Link, router, usePage } from '@inertiajs/react';
import { index } from '@/routes/sales';
import { formatPriceMinor } from '@/lib/catalog/form-values';
import StockMovements from '@/components/sales/stock-movements';
import { useEffect, useMemo, useState } from 'react';
import { Button } from '@/components/ui/button';
import CorrectionDialog from '@/components/sales/correction-dialog';
import { useSaleReversal } from '@/hooks/use-sale-reversal';
import type { SaleShowProps } from '@/types/sales';

export default function SaleShow(props: SaleShowProps) {
    const { business, returnTo, restockCandidates } = props;
    const { auth } = usePage().props;
    const scope = useMemo(
        () => ({
            userId: auth.user.id,
            storeId: business.storeId,
            storeSlug: business.storeSlug,
            saleId: props.sale.id,
        }),
        [auth.user.id, business.storeId, business.storeSlug, props.sale.id],
    );
    const refund = useSaleReversal(scope);
    const [open, setOpen] = useState(false);
    const recorded = refund.state?.reversal ?? props.sale.reversal;
    const sale = recorded
        ? { ...props.sale, status: 'reversed' as const, reversal: recorded }
        : props.sale;
    useEffect(() => {
        if (refund.state?.status === 'recorded' && !props.sale.reversal)
            router.reload({ only: ['sale', 'restockCandidates'] });
    }, [refund.state?.status, props.sale.reversal]);
    if (!refund.error && !refund.state?.sessionValid)
        return (
            <div className="space-y-4 p-6">
                <p role="status">
                    {refund.state?.message ||
                        'Verifying your session and current refund status…'}
                </p>
                {refund.state?.status === 'unknown' && (
                    <p>
                        Refund recording status is unknown. Do not return money
                        again while checking this sale.
                    </p>
                )}
                <Button
                    disabled={!refund.session || refund.state?.busy}
                    onClick={() => void refund.session?.restore()}
                >
                    Check again
                </Button>
            </div>
        );
    const parameters = Object.fromEntries(
        Object.entries(returnTo.filters).filter(
            ([, v]) => v !== null && v !== '',
        ),
    );
    return (
        <div className="space-y-6 p-4 md:p-6">
            <Head title={sale.reference} />
            {refund.error && (
                <p role="alert">
                    Refund storage is unavailable. Recording is blocked.{' '}
                    {refund.error}
                </p>
            )}
            <Button
                className="min-h-11"
                disabled={!refund.state?.sessionValid}
                onClick={() => setOpen(true)}
            >
                {sale.reversal
                    ? 'View recorded refund'
                    : refund.state?.status === 'unknown' ||
                        refund.state?.status === 'submitting'
                      ? 'Check refund recording'
                      : 'Refund full sale'}
            </Button>
            {!open && refund.state?.status === 'unknown' && (
                <p role="status">
                    Refund recording status is unknown. Do not return money
                    again while checking this sale.
                </p>
            )}
            <CorrectionDialog
                open={open}
                onOpenChange={setOpen}
                sale={sale}
                candidates={restockCandidates}
                state={refund.state}
                onSubmit={(input) => void refund.session?.submit(input)}
                onCheck={() => void refund.session?.check()}
                onRetry={() => void refund.session?.retryOriginal()}
            />

            <Link
                className="inline-block min-h-11 content-center text-sm underline"
                href={index(business.storeSlug, {
                    query: { ...parameters, page: returnTo.page },
                })}
            >
                Back to sales
            </Link>
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 className="text-2xl font-semibold break-all">
                        {sale.reference}
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        {sale.status === 'reversed' ? 'Refunded' : 'Completed'}{' '}
                        ·{' '}
                        {new Date(sale.occurredAt).toLocaleString('en-PH', {
                            timeZone: 'Asia/Manila',
                        })}{' '}
                        · Owner #{sale.actorId}
                    </p>
                </div>
            </div>
            <section
                className="grid gap-4 rounded-lg border p-4 sm:grid-cols-3"
                aria-label="Sale amounts"
            >
                <p>Original total PHP {formatPriceMinor(sale.totalMinor)}</p>
                <p>
                    Refunded PHP{' '}
                    {formatPriceMinor(sale.reversal?.totalMinor ?? 0)}
                </p>
                <p>
                    Net PHP{' '}
                    {formatPriceMinor(
                        sale.totalMinor - (sale.reversal?.totalMinor ?? 0),
                    )}
                </p>
            </section>
            <section className="space-y-3 rounded-lg border p-4">
                <h2 className="font-semibold">Original items</h2>
                {sale.lines.map((line) => (
                    <article
                        className="space-y-1 border-b pb-3 last:border-0"
                        key={line.lineId}
                    >
                        <p className="break-words">
                            {line.quantity} × {line.name} · PHP{' '}
                            {formatPriceMinor(line.totalMinor)}
                        </p>
                        <p className="text-sm text-muted-foreground">
                            Unit price PHP{' '}
                            {formatPriceMinor(line.unitPriceMinor)}
                        </p>
                        {line.addons.map((addon) => (
                            <p key={addon.id} className="text-sm break-words">
                                + {addon.name} · PHP{' '}
                                {formatPriceMinor(addon.unitPriceMinor)} each
                            </p>
                        ))}
                        <details className="text-sm">
                            <summary className="min-h-11 cursor-pointer content-center">
                                Original recipe deductions per item
                            </summary>
                            {[
                                ...line.components,
                                ...line.addons.flatMap(
                                    (addon) => addon.components,
                                ),
                            ].map((part, i) => (
                                <p key={i}>
                                    {part.quantity} {part.unit} · Inventory #
                                    {part.inventoryItemId}
                                </p>
                            ))}
                        </details>
                    </article>
                ))}
            </section>
            <section className="space-y-2 rounded-lg border p-4">
                <h2 className="font-semibold">Original payment</h2>
                <p>
                    {sale.payment.input.method} · Received PHP{' '}
                    {formatPriceMinor(sale.payment.receivedMinor)}
                </p>
                {sale.payment.input.method === 'cash' ? (
                    <p>
                        Cash tender PHP{' '}
                        {formatPriceMinor(sale.payment.input.tenderedMinor)}
                    </p>
                ) : (
                    <p className="break-all">
                        Reference:{' '}
                        {sale.payment.input.reference ?? 'Not supplied'}
                    </p>
                )}
                <p>Change PHP {formatPriceMinor(sale.payment.changeMinor)}</p>
            </section>
            {sale.reversal && (
                <section className="space-y-2 rounded-lg border p-4">
                    <h2 className="font-semibold">Recorded full refund</h2>
                    <p>
                        PHP {formatPriceMinor(sale.reversal.totalMinor)} ·{' '}
                        {sale.reversal.refundMethod}
                    </p>
                    <p className="break-all">
                        Reference:{' '}
                        {sale.reversal.refundReference ?? 'Not supplied'}
                    </p>
                    <p className="break-words">{sale.reversal.reason}</p>
                    <p className="text-sm text-muted-foreground">
                        {sale.reversal.actorName} ·{' '}
                        {new Date(sale.reversal.occurredAt).toLocaleString(
                            'en-PH',
                            { timeZone: 'Asia/Manila' },
                        )}
                    </p>
                </section>
            )}
            <section className="space-y-3">
                <h2 className="font-semibold">Sale stock movements</h2>
                <StockMovements
                    storeSlug={business.storeSlug}
                    movements={sale.stockMovements.filter(
                        (m) => m.kind === 'sale',
                    )}
                />
            </section>
            {sale.reversal && (
                <section className="space-y-3">
                    <h2 className="font-semibold">Returned stock movements</h2>
                    <StockMovements
                        storeSlug={business.storeSlug}
                        movements={sale.stockMovements.filter(
                            (m) => m.kind === 'reversal',
                        )}
                    />
                </section>
            )}
        </div>
    );
}
