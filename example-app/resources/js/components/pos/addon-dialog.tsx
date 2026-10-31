import { useState } from 'react';
import type { CatalogItem, CatalogListItem } from '@/types';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogTitle,
    DialogDescription,
    DialogFooter,
} from '@/components/ui/dialog';
export default function AddonDialog({
    product,
    addons,
    selected,
    apply,
    close,
}: {
    product: CatalogItem;
    addons: CatalogListItem[];
    selected: number[];
    apply: (ids: number[]) => void;
    close: () => void;
}) {
    const [ids, setIds] = useState(selected);
    return (
        <Dialog
            open
            onOpenChange={(open) => {
                if (!open) close();
            }}
        >
            <DialogContent className="max-h-[85dvh] overflow-y-auto [&_[data-slot=dialog-close]]:min-h-11">
                <DialogTitle>Add-ons · {product.name}</DialogTitle>
                <DialogDescription>
                    Each selection applies once per product unit. Leave all
                    unchecked for no add-ons.
                </DialogDescription>
                {product.allowedAddonIds.map((id) => {
                    const addon = addons.find((x) => x.id === id);
                    const unavailable =
                        !addon?.active ||
                        addon.availability.issues.some(
                            (x) => x.code === 'invalid_configuration',
                        );
                    return (
                        <label
                            key={id}
                            className="flex min-h-11 items-start gap-3 rounded border p-3"
                        >
                            <input
                                type="checkbox"
                                className="mt-1 size-5"
                                checked={ids.includes(id)}
                                disabled={unavailable && !ids.includes(id)}
                                onChange={(e) =>
                                    setIds(
                                        e.target.checked
                                            ? [...ids, id]
                                            : ids.filter((x) => x !== id),
                                    )
                                }
                            />
                            <span className="min-w-0 break-words">
                                {addon?.name ?? 'Unavailable add-on'}{' '}
                                {addon &&
                                    `· PHP ${(addon.priceMinor / 100).toFixed(2)}`}
                                {unavailable && (
                                    <span className="block text-sm text-destructive">
                                        Unavailable — remove this selection.
                                    </span>
                                )}
                            </span>
                        </label>
                    );
                })}
                <DialogFooter>
                    <Button
                        variant="outline"
                        className="min-h-11"
                        onClick={close}
                    >
                        Cancel
                    </Button>
                    <Button className="min-h-11" onClick={() => apply(ids)}>
                        Apply add-ons
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
