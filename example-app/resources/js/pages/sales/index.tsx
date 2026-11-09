import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { index, show } from '@/routes/sales';
import { formatPriceMinor } from '@/lib/catalog/form-values';
import type { SalesIndexProps } from '@/types/sales';

export default function SalesIndex({
    business,
    sales,
    filters,
}: SalesIndexProps) {
    const [busy, setBusy] = useState(false);
    const { errors } = usePage().props;
    const parameters = Object.fromEntries(
        Object.entries(filters).filter(([, v]) => v !== null && v !== ''),
    );
    return (
        <div className="space-y-6 p-4 md:p-6">
            <Head title="Sales" />
            <div>
                <h1 className="text-2xl font-semibold">Sales</h1>
                <p className="text-sm text-muted-foreground">
                    Review recorded sales and full refunds. Dates use
                    Asia/Manila.
                </p>
            </div>
            <form
                key={JSON.stringify(filters)}
                className="grid gap-3 rounded-lg border p-4 sm:grid-cols-2 lg:grid-cols-3"
                onSubmit={(event) => {
                    event.preventDefault();
                    const values = Object.fromEntries(
                        new FormData(event.currentTarget).entries(),
                    );
                    router.get(
                        index(business.storeSlug).url,
                        { ...values, page: 1 },
                        {
                            preserveState: true,
                            replace: true,
                            onStart: () => setBusy(true),
                            onFinish: () => setBusy(false),
                        },
                    );
                }}
            >
                <label className="space-y-1 text-sm">
                    Reference
                    <Input
                        name="reference"
                        defaultValue={filters.reference}
                        maxLength={255}
                        className="min-h-11"
                    />
                </label>
                <label className="space-y-1 text-sm">
                    Sale date from
                    <Input
                        type="date"
                        name="dateFrom"
                        defaultValue={filters.dateFrom ?? ''}
                        className="min-h-11"
                    />
                </label>
                <label className="space-y-1 text-sm">
                    Sale date to
                    <Input
                        type="date"
                        name="dateTo"
                        defaultValue={filters.dateTo ?? ''}
                        className="min-h-11"
                    />
                </label>
                <label className="space-y-1 text-sm">
                    Original payment
                    <select
                        name="payment"
                        defaultValue={filters.payment ?? ''}
                        className="min-h-11 w-full rounded-md border bg-background px-3"
                    >
                        <option value="">All methods</option>
                        <option value="cash">Cash</option>
                        <option value="gcash">GCash</option>
                        <option value="maya">Maya</option>
                    </select>
                </label>
                <label className="space-y-1 text-sm">
                    Status
                    <select
                        name="status"
                        defaultValue={filters.status ?? ''}
                        className="min-h-11 w-full rounded-md border bg-background px-3"
                    >
                        <option value="">All statuses</option>
                        <option value="completed">Completed</option>
                        <option value="reversed">Refunded</option>
                    </select>
                </label>
                <Button className="min-h-11 self-end" disabled={busy}>
                    Apply filters
                </Button>
                {Object.entries(errors).map(([key, error]) => (
                    <p
                        key={key}
                        role="alert"
                        className="text-sm text-destructive"
                    >
                        {error}
                    </p>
                ))}
            </form>
            <p role="status" className="text-sm text-muted-foreground">
                {busy ? 'Loading sales…' : `${sales.total} recorded sales`}
            </p>
            {!sales.data.length && (
                <div className="rounded-lg border p-8 text-center">
                    {Object.keys(parameters).length
                        ? 'No sales match these filters.'
                        : 'No recorded sales yet.'}
                </div>
            )}
            <div className="space-y-3">
                {sales.data.map((sale) => (
                    <article
                        key={sale.id}
                        className="space-y-3 rounded-lg border p-4"
                    >
                        <div className="flex flex-wrap items-center justify-between gap-2">
                            <Link
                                className="min-h-11 content-center font-semibold underline underline-offset-4"
                                href={show(
                                    {
                                        current_team: business.storeSlug,
                                        sale: sale.id,
                                    },
                                    {
                                        query: {
                                            ...parameters,
                                            page: sales.currentPage,
                                        },
                                    },
                                )}
                            >
                                {sale.reference}
                            </Link>
                            <span className="rounded-full bg-muted px-3 py-1 text-sm">
                                {sale.status === 'reversed'
                                    ? 'Refunded'
                                    : 'Completed'}
                            </span>
                        </div>
                        <p className="text-sm text-muted-foreground">
                            {new Date(sale.occurredAt).toLocaleString('en-PH', {
                                timeZone: 'Asia/Manila',
                            })}{' '}
                            · {sale.paymentMethod}
                        </p>
                        <dl className="grid gap-3 text-sm sm:grid-cols-3">
                            {[
                                ['Original total', sale.totalMinor],
                                ['Refunded', sale.refundedMinor],
                                ['Net', sale.netMinor],
                            ].map(([label, value]) => (
                                <div key={label}>
                                    <dt className="text-muted-foreground">
                                        {label}
                                    </dt>
                                    <dd className="font-medium tabular-nums">
                                        PHP {formatPriceMinor(Number(value))}
                                    </dd>
                                </div>
                            ))}
                        </dl>
                    </article>
                ))}
            </div>
            <nav
                aria-label="Sales pages"
                className="flex flex-wrap items-center gap-4"
            >
                <Button
                    asChild
                    variant="outline"
                    disabled={sales.currentPage <= 1}
                    className="min-h-11"
                >
                    <Link
                        aria-disabled={sales.currentPage <= 1}
                        className={
                            sales.currentPage <= 1
                                ? 'pointer-events-none opacity-50'
                                : ''
                        }
                        href={index(business.storeSlug, {
                            query: {
                                ...parameters,
                                page: Math.max(1, sales.currentPage - 1),
                            },
                        })}
                    >
                        Previous
                    </Link>
                </Button>
                <span className="text-sm">
                    Page {sales.currentPage} of {sales.lastPage}
                </span>
                <Button
                    asChild
                    variant="outline"
                    disabled={sales.currentPage >= sales.lastPage}
                    className="min-h-11"
                >
                    <Link
                        aria-disabled={sales.currentPage >= sales.lastPage}
                        className={
                            sales.currentPage >= sales.lastPage
                                ? 'pointer-events-none opacity-50'
                                : ''
                        }
                        href={index(business.storeSlug, {
                            query: {
                                ...parameters,
                                page: sales.currentPage + 1,
                            },
                        })}
                    >
                        Next
                    </Link>
                </Button>
            </nav>
        </div>
    );
}
