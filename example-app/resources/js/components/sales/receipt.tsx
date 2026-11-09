import type { Sale } from '@/types/sales';
import { formatPriceMinor } from '@/lib/catalog/form-values';
const date = (value: string) =>
    new Date(value).toLocaleString('en-PH', { timeZone: 'Asia/Manila' });
export default function Receipt({
    sale,
    storeName,
}: {
    sale: Sale;
    storeName: string;
}) {
    return (
        <section
            className="sales-receipt mx-auto w-full max-w-xl space-y-5 rounded-lg border bg-white p-5 text-neutral-950 sm:p-8"
            aria-label="Sale receipt"
        >
            <header className="receipt-header space-y-1 border-b border-neutral-300 pb-4 text-center">
                <h2 className="text-xl font-semibold break-words">
                    {storeName}
                </h2>
                <p className="font-medium break-all">{sale.reference}</p>
                <p className="text-sm">{date(sale.occurredAt)} · Asia/Manila</p>
            </header>
            <div className="space-y-4">
                {sale.lines.map((line) => (
                    <article
                        key={line.lineId}
                        className="receipt-line space-y-1"
                    >
                        <div className="flex items-start justify-between gap-4">
                            <p className="min-w-0 break-words">
                                {line.quantity} × {line.name}
                            </p>
                            <p className="shrink-0 tabular-nums">
                                PHP {formatPriceMinor(line.totalMinor)}
                            </p>
                        </div>
                        <p className="text-sm">
                            Unit price PHP{' '}
                            {formatPriceMinor(line.unitPriceMinor)}
                        </p>
                        {line.addons.map((addon) => (
                            <p key={addon.id} className="text-sm break-words">
                                + {addon.name} · {line.quantity} × PHP{' '}
                                {formatPriceMinor(addon.unitPriceMinor)}
                            </p>
                        ))}
                    </article>
                ))}
            </div>
            <div className="receipt-totals space-y-2 border-t border-neutral-300 pt-4">
                <p className="flex flex-wrap justify-between gap-2 font-semibold">
                    <span>Original total</span>
                    <span>PHP {formatPriceMinor(sale.totalMinor)}</span>
                </p>
                <p>
                    Recorded payment: {sale.payment.input.method} · PHP{' '}
                    {formatPriceMinor(sale.payment.receivedMinor)}
                </p>
                {sale.payment.input.method === 'cash' ? (
                    <p>
                        Cash tender: PHP{' '}
                        {formatPriceMinor(sale.payment.input.tenderedMinor)}
                    </p>
                ) : (
                    <p className="break-all">
                        Payment reference:{' '}
                        {sale.payment.input.reference ?? 'Not supplied'}
                    </p>
                )}
                <p>Change: PHP {formatPriceMinor(sale.payment.changeMinor)}</p>
            </div>
            {sale.reversal && (
                <div className="receipt-refund space-y-2 border-t-2 border-neutral-500 pt-4">
                    <h3 className="font-semibold">Recorded refund</h3>
                    <p>
                        Full amount: PHP{' '}
                        {formatPriceMinor(sale.reversal.totalMinor)}
                    </p>
                    <p>Actual refund method: {sale.reversal.refundMethod}</p>
                    <p className="break-all">
                        Refund reference:{' '}
                        {sale.reversal.refundReference ?? 'Not supplied'}
                    </p>
                    <p className="text-sm">
                        {date(sale.reversal.occurredAt)} · Asia/Manila
                    </p>
                </div>
            )}
        </section>
    );
}
