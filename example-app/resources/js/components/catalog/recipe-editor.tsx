import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import type { CatalogEditorProps, RecipeComponent } from '@/types/catalog';
export function RecipeEditor({
    components,
    inventory,
    change,
    errors,
}: {
    components: RecipeComponent[];
    inventory: CatalogEditorProps['inventory'];
    change: (rows: RecipeComponent[]) => void;
    errors: Record<string, unknown>;
}) {
    return (
        <section className="space-y-3 rounded-md border p-4">
            <h2 className="font-semibold">Recipe per unit sold</h2>
            <p className="text-sm text-muted-foreground">
                Ingredients and supplies are deducted per sold unit. Shortages
                warn and can become negative.
            </p>
            {components.map((part, i) => (
                <div
                    key={i}
                    className="grid items-end gap-2 sm:grid-cols-[1fr_8rem_auto_auto]"
                >
                    <label className="min-w-0 text-sm">
                        Component
                        <select
                            className="mt-1 w-full rounded-md border bg-background p-2"
                            aria-label={`Component ${i + 1}`}
                            aria-invalid={Boolean(
                                errors[`components.${i}.inventoryItemId`],
                            )}
                            aria-describedby={
                                errors[`components.${i}.inventoryItemId`]
                                    ? 'product-errors'
                                    : undefined
                            }
                            value={part.inventoryItemId || ''}
                            onChange={(e) => {
                                const stock = inventory.find(
                                    (s) => s.id === Number(e.target.value),
                                );
                                if (stock)
                                    change(
                                        components.map((row, j) =>
                                            j === i
                                                ? {
                                                      ...row,
                                                      inventoryItemId: stock.id,
                                                      unit: stock.unit,
                                                  }
                                                : row,
                                        ),
                                    );
                            }}
                        >
                            <option value="">Choose ingredient/supply</option>
                            {inventory
                                .filter(
                                    (stock) =>
                                        stock.id === part.inventoryItemId ||
                                        (stock.active &&
                                            !components.some(
                                                (row) =>
                                                    row.inventoryItemId ===
                                                    stock.id,
                                            )),
                                )
                                .map((stock) => (
                                    <option key={stock.id} value={stock.id}>
                                        {stock.name}
                                        {!stock.active
                                            ? ' (inactive — repair before activation)'
                                            : ''}
                                    </option>
                                ))}
                        </select>
                    </label>
                    <label className="text-sm">
                        Quantity
                        <Input
                            required
                            inputMode="decimal"
                            aria-label={`Component ${i + 1} quantity`}
                            aria-invalid={Boolean(
                                errors[`components.${i}.quantity`],
                            )}
                            aria-describedby={
                                errors[`components.${i}.quantity`]
                                    ? 'product-errors'
                                    : undefined
                            }
                            value={part.quantity}
                            onChange={(e) =>
                                change(
                                    components.map((row, j) =>
                                        j === i
                                            ? {
                                                  ...row,
                                                  quantity: e.target.value,
                                              }
                                            : row,
                                    ),
                                )
                            }
                        />
                    </label>
                    <span className="py-2 text-sm">{part.unit}</span>
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() =>
                            change(components.filter((_, j) => j !== i))
                        }
                        aria-label={`Remove component ${i + 1}`}
                    >
                        Remove
                    </Button>
                </div>
            ))}
            <Button
                type="button"
                variant="outline"
                onClick={() =>
                    change([
                        ...components,
                        { inventoryItemId: 0, unit: 'g', quantity: '' },
                    ])
                }
            >
                Add component
            </Button>
        </section>
    );
}
