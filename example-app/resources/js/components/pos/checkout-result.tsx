import { useEffect, useRef } from 'react';
import type { CheckoutState } from '@/lib/pos/checkout-session';
import { Button } from '@/components/ui/button';
import { formatPriceMinor } from '@/lib/catalog/form-values';
export default function CheckoutResult({
    state,
    onCheck,
    onRetry,
    onNewSale,
}: {
    state: CheckoutState;
    onCheck: () => void;
    onRetry: () => void;
    onNewSale: () => void;
}) {
    const heading = useRef<HTMLHeadingElement>(null);
    useEffect(() => {
        heading.current?.focus();
    }, [state.state]);
    return (
        <section aria-live="polite" className="space-y-4">
            <h2 tabIndex={-1} ref={heading} className="text-2xl font-semibold">
                {state.state === 'confirmed'
                    ? 'Sale recorded'
                    : state.state === 'submitting'
                      ? 'Recording payment…'
                      : 'Payment status unknown'}
            </h2>
            {state.state === 'confirmed' && state.sale ? (
                <>
                    <p className="text-xl font-semibold">
                        {state.sale.reference}
                    </p>
                    {state.sale.lines.map((l) => (
                        <p key={l.lineId}>
                            {l.quantity} × {l.name}
                            {l.addons.length
                                ? ' · ' + l.addons.map((a) => a.name).join(', ')
                                : ''}{' '}
                            · PHP {formatPriceMinor(l.totalMinor)}
                        </p>
                    ))}
                    <p>Total PHP {formatPriceMinor(state.sale.totalMinor)}</p>
                    <p>
                        {state.sale.payment.input.method} · Recorded PHP{' '}
                        {formatPriceMinor(state.sale.payment.receivedMinor)}
                    </p>
                    <p>
                        Change PHP{' '}
                        {formatPriceMinor(state.sale.payment.changeMinor)}
                    </p>
                    <Button
                        className="min-h-11"
                        disabled={
                            !state.durable || !state.sessionValid || state.busy
                        }
                        onClick={onNewSale}
                    >
                        New Sale
                    </Button>
                </>
            ) : (
                <>
                    <p>
                        Payment recording status is unknown. Do not collect
                        payment again while checking this sale.
                    </p>
                    <Button
                        className="min-h-11"
                        disabled={state.busy || !state.sessionValid}
                        onClick={onCheck}
                    >
                        Check again
                    </Button>
                    {['pending', 'not_found'].includes(state.lookup ?? '') && (
                        <Button
                            className="min-h-11"
                            variant="outline"
                            disabled={state.busy || !state.sessionValid}
                            onClick={onRetry}
                        >
                            {state.lookup === 'pending'
                                ? 'Resume same attempt'
                                : 'Retry original attempt'}
                        </Button>
                    )}
                </>
            )}
            {state.message && (
                <p role="alert" className="text-sm break-words">
                    {state.message}
                </p>
            )}
            {state.state === 'confirmed' && !state.durable && (
                <Button className="min-h-11" onClick={onCheck}>
                    Save confirmation again
                </Button>
            )}
        </section>
    );
}
