import type { CheckoutQuote } from '@/types/pos';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogTitle,
    DialogDescription,
} from '@/components/ui/dialog';
import { formatPriceMinor } from '@/lib/catalog/form-values';
export default function CheckoutReview({
    quote,
    onConfirm,
    onBack,
    disabled,
    busy,
}: {
    quote: CheckoutQuote;
    onConfirm: () => void;
    onBack: () => void;
    disabled: boolean;
    busy: boolean;
}) {
    return (
        <Dialog
            open
            onOpenChange={(open) => {
                if (!open && !busy) onBack();
            }}
        >
            <DialogContent className="max-h-[85dvh] overflow-y-auto [&>button]:min-h-11 [&>button]:min-w-11">
                <DialogTitle>Review payment recording</DialogTitle>
                <DialogDescription>
                    Confirm the current items, total and warnings. External
                    money is received outside this app.
                </DialogDescription>
                <ul className="space-y-2">
                    {quote.lines.map((l) => (
                        <li key={l.lineId} className="break-words">
                            {l.quantity} × {l.name}
                            {l.addons.length
                                ? ` · ${l.addons.map((a) => a.name).join(', ')}`
                                : ''}{' '}
                            · PHP {formatPriceMinor(l.totalMinor)}
                        </li>
                    ))}
                </ul>
                <p className="text-xl font-semibold break-all">
                    Total PHP {formatPriceMinor(quote.totalMinor)}
                </p>
                <p>Method: {quote.payment?.method}</p>
                {quote.payment?.method === 'cash' && (
                    <p>Change PHP {formatPriceMinor(quote.changeMinor ?? 0)}</p>
                )}
                {quote.warnings.map((w) => (
                    <p
                        key={w.id}
                        className="rounded-md border p-3 text-sm break-words"
                    >
                        Warning: {w.message}
                    </p>
                ))}
                <p className="text-sm">
                    Recording confirms that you reviewed all warnings above.
                </p>
                <Button
                    className="min-h-11"
                    disabled={disabled || busy}
                    onClick={onConfirm}
                >
                    {busy ? 'Recording…' : 'Confirm payment recording'}
                </Button>
                <Button
                    className="min-h-11"
                    variant="outline"
                    disabled={busy}
                    onClick={onBack}
                >
                    Back to payment
                </Button>
            </DialogContent>
        </Dialog>
    );
}
