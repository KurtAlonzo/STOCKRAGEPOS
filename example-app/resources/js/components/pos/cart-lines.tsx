import type { CartLine, CatalogItem, CheckoutQuote } from '@/types';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
export default function CartLines({
    lines,
    catalog,
    quote,
    buffers,
    errors,
    quantity,
    edit,
    remove,
}: {
    lines: CartLine[];
    catalog: ReadonlyMap<number, CatalogItem>;
    quote: CheckoutQuote | null;
    buffers: Record<string, string>;
    errors: Record<string, string>;
    quantity: (id: string, value: string) => void;
    edit: (line: CartLine) => void;
    remove: (id: string) => void;
}) {
    return (
        <section aria-label="Cart lines" className="space-y-5">
            <h2 className="text-xl font-semibold">
                Cart{' '}
                <span className="text-muted-foreground">({lines.length})</span>
            </h2>
            {!lines.length && (
                <p className="text-muted-foreground">
                    Select products to prepare an order.
                </p>
            )}
            {lines.map((line) => {
                const product = catalog.get(line.productId),
                    snapshot = quote?.lines.find((x) => x.lineId === line.id);
                return (
                    <article key={line.id} className="space-y-2 border-b pb-4">
                        <h3 className="font-semibold break-words">
                            {snapshot?.name ??
                                product?.name ??
                                'Unavailable product'}
                        </h3>
                        <p className="text-sm break-words text-muted-foreground">
                            {line.addonIds
                                .map(
                                    (id) =>
                                        catalog.get(id)?.name ?? `Add-on ${id}`,
                                )
                                .join(', ') || 'No add-ons'}
                        </p>
                        <label htmlFor={`qty-${line.id}`} className="text-sm">
                            Quantity
                        </label>
                        <Input
                            id={`qty-${line.id}`}
                            className="min-h-11"
                            inputMode="numeric"
                            value={buffers[line.id] ?? String(line.quantity)}
                            aria-invalid={!!errors[line.id]}
                            aria-describedby={
                                errors[line.id] ? `error-${line.id}` : undefined
                            }
                            onChange={(e) => quantity(line.id, e.target.value)}
                        />
                        {errors[line.id] && (
                            <p
                                id={`error-${line.id}`}
                                className="text-sm text-destructive"
                            >
                                {errors[line.id]}
                            </p>
                        )}
                        <div className="flex flex-wrap gap-2">
                            {!!(
                                product?.allowedAddonIds.length ||
                                line.addonIds.length
                            ) && (
                                <Button
                                    className="min-h-11"
                                    variant="outline"
                                    onClick={() => edit(line)}
                                >
                                    Edit add-ons
                                </Button>
                            )}
                            <Button
                                className="min-h-11"
                                variant="ghost"
                                onClick={() => remove(line.id)}
                            >
                                Remove
                                <span className="sr-only">
                                    {' '}
                                    {product?.name}
                                </span>
                            </Button>
                        </div>
                    </article>
                );
            })}
        </section>
    );
}
