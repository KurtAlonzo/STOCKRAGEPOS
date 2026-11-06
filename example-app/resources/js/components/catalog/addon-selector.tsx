import type { CatalogItem } from '@/types/catalog';
import { formatPriceMinor } from '@/lib/catalog/form-values';
export function AddonSelector({
    addons,
    selected,
    change,
}: {
    addons: CatalogItem[];
    selected: number[];
    change: (ids: number[]) => void;
}) {
    return (
        <fieldset className="space-y-3 rounded-md border p-4">
            <legend className="px-1 font-semibold">Allowed add-ons</legend>
            <p className="text-sm text-muted-foreground">
                Each selected add-on applies once per sold product unit.
            </p>
            {!addons.length && (
                <p className="text-sm">
                    Create an add-on in Catalog to offer it here.
                </p>
            )}
            {addons.map((addon) => (
                <label
                    key={addon.id}
                    className="flex items-start gap-2 text-sm"
                >
                    <input
                        type="checkbox"
                        checked={selected.includes(addon.id)}
                        disabled={!addon.active && !selected.includes(addon.id)}
                        onChange={(e) =>
                            change(
                                (e.target.checked
                                    ? [...selected, addon.id]
                                    : selected.filter((id) => id !== addon.id)
                                ).sort((a, b) => a - b),
                            )
                        }
                    />
                    <span className="break-words">
                        {addon.name} · ₱{formatPriceMinor(addon.priceMinor)} ·{' '}
                        {'stockBehavior' in addon &&
                        addon.stockBehavior === 'direct'
                            ? 'Direct stock'
                            : 'Ingredient/supply recipe'}
                        {!addon.active
                            ? ' · Inactive — remove or repair before activation'
                            : ''}
                    </span>
                </label>
            ))}
        </fieldset>
    );
}
