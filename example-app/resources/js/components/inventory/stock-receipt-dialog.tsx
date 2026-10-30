import { useForm, router } from '@inertiajs/react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogDescription,
} from '@/components/ui/dialog';
import { useUnsavedChanges } from '@/hooks/use-unsaved-changes';
import { store as receive } from '@/routes/inventory/receipts';
import { store as adjust } from '@/routes/inventory/adjustments';
import type { InventoryItem } from '@/types/inventory';
import { projectQuantity } from '@/lib/catalog/form-values';

export function StockReceiptDialog({
    slug,
    item,
    adjustment = false,
    close,
}: {
    slug: string;
    item: InventoryItem;
    adjustment?: boolean;
    close: () => void;
}) {
    const form = useForm({ amount: '', reason: '' });
    const [uncertain, setUncertain] = useState(false);
    const serverErrors: Record<string, unknown> = form.errors;
    const mayClose = useUnsavedChanges(form.isDirty, form.processing);
    const projected = projectQuantity(
        item.balance,
        form.data.amount,
        item.unit,
    );
    return (
        <Dialog
            open
            onOpenChange={(open) => {
                if (!open && !form.processing && !uncertain && mayClose(close))
                    close();
            }}
        >
            <DialogContent>
                {mayClose.dialog}
                <DialogHeader>
                    <DialogTitle>
                        {adjustment ? 'Correct stock' : 'Receive stock'} ·{' '}
                        {item.name}
                    </DialogTitle>
                    <DialogDescription>
                        Current balance {item.balance} {item.unit}.{' '}
                        {adjustment
                            ? 'Enter the signed change, not the final balance.'
                            : 'Opening stock is recorded as a receipt.'}
                    </DialogDescription>
                </DialogHeader>
                <form
                    className="space-y-4"
                    onSubmit={(event) => {
                        event.preventDefault();
                        if (uncertain || form.processing) return;
                        mayClose.beforeSubmit();
                        form.transform((data) => ({
                            inventoryItemId: item.id,
                            [adjustment ? 'delta' : 'quantity']: data.amount,
                            reason: data.reason,
                            expectedRevision: item.revision,
                        }));
                        form.post((adjustment ? adjust : receive)(slug).url, {
                            onSuccess: close,
                            onNetworkError: () => {
                                setUncertain(true);
                                return false;
                            },
                            onHttpException: () => {
                                setUncertain(true);
                                return false;
                            },
                            onCancel: () => setUncertain(true),
                        });
                    }}
                >
                    <label className="block space-y-1">
                        {adjustment ? 'Change' : 'Received quantity'} (
                        {item.unit})
                        <Input
                            aria-invalid={Boolean(
                                serverErrors.quantity || serverErrors.delta,
                            )}
                            aria-describedby={
                                serverErrors.quantity || serverErrors.delta
                                    ? 'stock-errors'
                                    : undefined
                            }
                            required
                            inputMode="decimal"
                            value={form.data.amount}
                            onChange={(e) =>
                                form.setData('amount', e.target.value)
                            }
                        />
                    </label>
                    {projected !== null && (
                        <p
                            className={
                                projected.startsWith('-')
                                    ? 'text-destructive'
                                    : 'text-muted-foreground'
                            }
                        >
                            Projected balance: {projected} {item.unit}
                            {projected.startsWith('-') &&
                                (item.shortagePolicy === 'block'
                                    ? ' — cannot save negative direct stock.'
                                    : ' — warning: ingredient/supply stock will be negative.')}
                        </p>
                    )}
                    <label className="block space-y-1">
                        Reason
                        <Input
                            aria-invalid={Boolean(serverErrors.reason)}
                            aria-describedby={
                                serverErrors.reason ? 'stock-errors' : undefined
                            }
                            required
                            maxLength={1000}
                            value={form.data.reason}
                            onChange={(e) =>
                                form.setData('reason', e.target.value)
                            }
                        />
                    </label>
                    <div
                        id="stock-errors"
                        role="alert"
                        className="text-sm text-destructive"
                    >
                        {uncertain && (
                            <p>
                                The result is uncertain. Reload the balance and
                                movements before recording another change.
                            </p>
                        )}
                        {Object.entries(form.errors).map(([key, error]) => (
                            <p key={key}>{error}</p>
                        ))}
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Button disabled={form.processing || uncertain}>
                            {form.processing
                                ? 'Recording…'
                                : 'Record stock change'}
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            disabled={form.processing || uncertain}
                            onClick={() => {
                                if (mayClose(close)) close();
                            }}
                        >
                            Cancel
                        </Button>
                        <Button
                            type="button"
                            variant="ghost"
                            disabled={form.processing}
                            onClick={() => {
                                const reload = () => {
                                    mayClose.beforeSubmit();
                                    close();
                                    router.reload();
                                };
                                if (mayClose(reload)) reload();
                            }}
                        >
                            Reload balance/history
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}
