import { useForm } from '@inertiajs/react';
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
import { store, update } from '@/routes/inventory/items';
import type { InventoryItem } from '@/types/inventory';

export function ItemDialog({
    slug,
    item,
    close,
}: {
    slug: string;
    item: InventoryItem | null;
    close: () => void;
}) {
    const form = useForm({
        name: item?.name ?? '',
        kind: item?.kind ?? 'ingredient',
        unit: item?.unit ?? 'g',
        active: item?.active ?? true,
        lowStockThreshold: item?.lowStockThreshold ?? '',
    });
    const serverErrors: Record<string, unknown> = form.errors;
    const mayClose = useUnsavedChanges(form.isDirty, form.processing);
    const owned = item?.kind === 'retail' || item?.kind === 'addon';
    function submit(event: React.FormEvent) {
        event.preventDefault();
        mayClose.beforeSubmit();
        form.transform((data) =>
            item
                ? {
                      name: data.name,
                      active: data.active,
                      lowStockThreshold: data.lowStockThreshold || null,
                      expectedRevision: item.revision,
                  }
                : {
                      ...data,
                      lowStockThreshold: data.lowStockThreshold || null,
                  },
        );
        const options = {
            onSuccess: () => {
                form.setDefaults();
                close();
            },
        };
        if (item)
            form.patch(
                update({ current_team: slug, inventoryItem: item.id }).url,
                options,
            );
        else form.post(store(slug).url, options);
    }
    return (
        <Dialog
            open
            onOpenChange={(open) => {
                if (!open && !form.processing && mayClose(close)) close();
            }}
        >
            <DialogContent>
                {mayClose.dialog}
                <DialogHeader>
                    <DialogTitle>
                        {item
                            ? 'Edit inventory item'
                            : 'Add ingredient or supply'}
                    </DialogTitle>
                    <DialogDescription>
                        Quantities use the configured base unit. Opening stock
                        is received after saving.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="space-y-4">
                    <label className="block space-y-1">
                        Name
                        <Input
                            aria-invalid={Boolean(serverErrors.name)}
                            aria-describedby={
                                serverErrors.name
                                    ? 'inventory-errors'
                                    : undefined
                            }
                            required
                            maxLength={255}
                            disabled={owned}
                            value={form.data.name}
                            onChange={(e) =>
                                form.setData('name', e.target.value)
                            }
                        />
                    </label>
                    {!item && (
                        <div className="grid grid-cols-2 gap-4">
                            <label>
                                Type
                                <select
                                    className="mt-1 w-full rounded-md border bg-background p-2"
                                    value={form.data.kind}
                                    onChange={(e) =>
                                        form.setData(
                                            'kind',
                                            e.target
                                                .value as InventoryItem['kind'],
                                        )
                                    }
                                >
                                    <option value="ingredient">
                                        Ingredient
                                    </option>
                                    <option value="supply">Supply</option>
                                </select>
                            </label>
                            <label>
                                Base unit
                                <select
                                    className="mt-1 w-full rounded-md border bg-background p-2"
                                    value={form.data.unit}
                                    onChange={(e) =>
                                        form.setData(
                                            'unit',
                                            e.target
                                                .value as InventoryItem['unit'],
                                        )
                                    }
                                >
                                    <option value="g">Grams (g)</option>
                                    <option value="ml">Millilitres (ml)</option>
                                    <option value="each">Each</option>
                                </select>
                            </label>
                        </div>
                    )}
                    <label className="block space-y-1">
                        Low-stock threshold ({form.data.unit}, optional)
                        <Input
                            inputMode="decimal"
                            value={form.data.lowStockThreshold}
                            onChange={(e) =>
                                form.setData(
                                    'lowStockThreshold',
                                    e.target.value,
                                )
                            }
                        />
                        <span className="text-xs text-muted-foreground">
                            Leave empty to disable low-stock alerts.
                        </span>
                    </label>
                    <label className="flex gap-2">
                        <input
                            type="checkbox"
                            checked={form.data.active}
                            disabled={owned}
                            onChange={(e) =>
                                form.setData('active', e.target.checked)
                            }
                        />
                        Active
                    </label>
                    <div
                        id="inventory-errors"
                        role="alert"
                        className="space-y-1 text-sm text-destructive"
                    >
                        {Object.entries(form.errors).map(([key, error]) => (
                            <p key={key}>{error}</p>
                        ))}
                    </div>
                    <div className="flex gap-2">
                        <Button disabled={form.processing}>
                            {form.processing ? 'Saving…' : 'Save item'}
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            disabled={form.processing}
                            onClick={() => {
                                if (mayClose(close)) close();
                            }}
                        >
                            Cancel
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}
