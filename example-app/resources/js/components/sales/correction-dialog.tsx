import { useEffect, useRef, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Dialog,
    DialogContent,
    DialogTitle,
    DialogDescription,
} from '@/components/ui/dialog';
import type { SaleShowProps, ReversalInput } from '@/types/sales';
import type { ReversalState } from '@/lib/sales/reversal-session';
import { formatPriceMinor } from '@/lib/catalog/form-values';
export default function CorrectionDialog({
    open,
    onOpenChange,
    sale,
    candidates,
    state,
    onSubmit,
    onCheck,
    onRetry,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    sale: SaleShowProps['sale'];
    candidates: SaleShowProps['restockCandidates'];
    state: ReversalState | null;
    onSubmit: (input: ReversalInput) => void;
    onCheck: () => void;
    onRetry: () => void;
}) {
    const [reason, setReason] = useState(''),
        [method, setMethod] = useState(sale.payment.input.method),
        [reference, setReference] = useState(''),
        [quantities, setQuantities] = useState<Record<number, string>>({}),
        [confirmed, setConfirmed] = useState(false),
        [online, setOnline] = useState(
            () => typeof navigator === 'undefined' || navigator.onLine,
        );
    const opener = useRef<HTMLElement | null>(null);
    const heading = useRef<HTMLHeadingElement>(null);
    useEffect(() => {
        const update = () => setOnline(navigator.onLine);
        window.addEventListener('online', update);
        window.addEventListener('offline', update);
        return () => {
            window.removeEventListener('online', update);
            window.removeEventListener('offline', update);
        };
    }, []);
    useEffect(() => {
        if (state?.status === 'rejected') setConfirmed(false);
    }, [state?.status]);
    useEffect(() => {
        if (open && state?.status === 'recorded') heading.current?.focus();
    }, [open, state?.status]);
    const editing =
        state?.sessionValid &&
        !state.busy &&
        ['editing', 'rejected'].includes(state.status);
    const unknown =
        state?.status === 'unknown' || state?.status === 'submitting';
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent
                className="max-h-[90dvh] overflow-y-auto [&>button]:min-h-11 [&>button]:min-w-11"
                onOpenAutoFocus={() => {
                    opener.current = document.activeElement as HTMLElement;
                }}
                onCloseAutoFocus={(event) => {
                    event.preventDefault();
                    opener.current?.focus();
                }}
            >
                <DialogTitle ref={heading} tabIndex={-1}>
                    {state?.status === 'recorded'
                        ? 'Full refund recorded'
                        : unknown
                          ? 'Refund recording status'
                          : 'Refund full sale'}
                </DialogTitle>
                <DialogDescription>
                    The app records an external refund. It does not transfer
                    money.
                </DialogDescription>
                <p className="text-xl font-semibold">
                    Full amount PHP {formatPriceMinor(sale.totalMinor)}
                </p>
                {unknown ? (
                    <div className="space-y-3" aria-live="polite">
                        <p>
                            Refund recording status is unknown. Do not return
                            money again while checking this sale.
                        </p>
                        <Button
                            className="min-h-11"
                            disabled={state.busy}
                            onClick={onCheck}
                        >
                            Check again
                        </Button>
                        {state.canRetry && (
                            <Button
                                className="min-h-11"
                                variant="outline"
                                disabled={state.busy || !online}
                                onClick={onRetry}
                            >
                                Retry original refund recording
                            </Button>
                        )}
                    </div>
                ) : state?.status === 'recorded' ? (
                    <div aria-live="polite">
                        <p>Refund recorded. Do not return money again.</p>
                        <p>
                            {state.reversal?.refundMethod} · Reference:{' '}
                            {state.reversal?.refundReference ?? 'Not supplied'}
                        </p>
                    </div>
                ) : (
                    <form
                        className="space-y-4"
                        onSubmit={(event) => {
                            event.preventDefault();
                            if (!editing || !confirmed || !online) return;
                            onSubmit({
                                idempotencyKey: crypto.randomUUID(),
                                reason,
                                refundMethod: method,
                                refundReference: reference.trim() || null,
                                refundConfirmed: true,
                                restock: Object.entries(quantities)
                                    .filter(([, q]) => q !== '')
                                    .map(([id, quantity]) => ({
                                        inventoryItemId: Number(id),
                                        quantity,
                                    })),
                            });
                        }}
                    >
                        <fieldset disabled={!editing} className="space-y-4">
                            <label className="block space-y-1 text-sm">
                                Reason
                                <textarea
                                    required
                                    maxLength={1000}
                                    value={reason}
                                    onChange={(e) => setReason(e.target.value)}
                                    className="min-h-24 w-full rounded-md border bg-background p-3"
                                />
                            </label>
                            <label className="block space-y-1 text-sm">
                                Actual refund method
                                <select
                                    value={method}
                                    onChange={(e) => {
                                        setMethod(
                                            e.target.value as typeof method,
                                        );
                                        setConfirmed(false);
                                    }}
                                    className="min-h-11 w-full rounded-md border bg-background px-3"
                                >
                                    <option value="cash">Cash</option>
                                    <option value="gcash">GCash</option>
                                    <option value="maya">Maya</option>
                                </select>
                            </label>
                            <label className="block space-y-1 text-sm">
                                Refund reference (optional)
                                <Input
                                    maxLength={255}
                                    value={reference}
                                    onChange={(e) =>
                                        setReference(e.target.value)
                                    }
                                    className="min-h-11"
                                />
                            </label>
                            <div className="space-y-3">
                                <h3 className="font-semibold">
                                    Returned stock (optional)
                                </h3>
                                <p className="text-sm text-muted-foreground">
                                    Nothing is returned to stock by default.
                                    Only returned retail items and directly
                                    stocked add-ons are eligible. Ingredients
                                    and supplies stay consumed.
                                </p>
                                {candidates.map((item) => (
                                    <label
                                        key={item.inventoryItemId}
                                        className="block space-y-1 text-sm"
                                    >
                                        Return {item.name} (maximum{' '}
                                        {item.maxQuantity})
                                        {!item.active ? ' · Inactive' : ''}
                                        <Input
                                            inputMode="numeric"
                                            value={
                                                quantities[
                                                    item.inventoryItemId
                                                ] ?? ''
                                            }
                                            onChange={(e) => {
                                                setQuantities({
                                                    ...quantities,
                                                    [item.inventoryItemId]:
                                                        e.target.value,
                                                });
                                                setConfirmed(false);
                                            }}
                                            className="min-h-11"
                                        />
                                    </label>
                                ))}
                            </div>
                            <label className="flex min-h-11 items-start gap-3 text-sm">
                                <input
                                    type="checkbox"
                                    checked={confirmed}
                                    onChange={(e) =>
                                        setConfirmed(e.target.checked)
                                    }
                                    className="mt-1 size-5 shrink-0"
                                />
                                I confirm the full amount was returned outside
                                the app using the selected method and the stock
                                selections are final.
                            </label>
                            <Button
                                className="min-h-11 w-full"
                                disabled={
                                    !confirmed || !reason.trim() || !online
                                }
                            >
                                Record full refund
                            </Button>
                        </fieldset>
                    </form>
                )}
                {state?.message && (
                    <p role="alert" className="text-sm break-words">
                        {state.message}
                    </p>
                )}
                {state?.error && (
                    <div role="alert" className="text-sm text-destructive">
                        <p>{state.error.message}</p>
                        {Object.entries(state.error.fieldErrors).map(
                            ([key, messages]) => (
                                <p key={key}>{messages.join(' ')}</p>
                            ),
                        )}
                    </div>
                )}
                {!online && (
                    <p role="status">Connect before recording a refund.</p>
                )}
            </DialogContent>
        </Dialog>
    );
}
