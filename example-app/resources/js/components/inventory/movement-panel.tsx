import { Link } from '@inertiajs/react';
import { index } from '@/routes/inventory';
import type { InventoryIndexProps } from '@/types/inventory';
export function MovementPanel({
    business,
    selectedItem,
    movements,
    filters,
}: InventoryIndexProps) {
    if (!selectedItem || !movements) return null;
    return (
        <section className="space-y-4 rounded-md border p-4">
            <h2 className="font-semibold">{selectedItem.name} movements</h2>
            {!movements.data.length && <p>No stock operations recorded.</p>}
            {movements.data.map((m) => (
                <article key={m.id} className="space-y-1 border-t pt-3 text-sm">
                    <p className="font-medium">
                        {m.kind === 'receipt'
                            ? 'Stock received'
                            : 'Stock correction'}{' '}
                        · {m.delta} {m.unit}
                    </p>
                    <p>
                        {m.before} → {m.after} {m.unit} · {m.itemName}
                    </p>
                    <p className="break-words">{m.reason}</p>
                    <p className="text-muted-foreground">
                        {m.actorName} ·{' '}
                        {new Date(m.occurredAt).toLocaleString('en-PH', {
                            timeZone: business.timezone,
                        })}{' '}
                        · Operation #{m.sourceId}
                    </p>
                </article>
            ))}
            <nav className="flex gap-4" aria-label="Movement pages">
                {movements.currentPage > 1 && (
                    <Link
                        href={index(business.storeSlug, {
                            query: {
                                ...filters,
                                itemId: selectedItem.id,
                                movementPage: movements.currentPage - 1,
                            },
                        })}
                    >
                        Previous movements
                    </Link>
                )}
                {movements.currentPage < movements.lastPage && (
                    <Link
                        href={index(business.storeSlug, {
                            query: {
                                ...filters,
                                itemId: selectedItem.id,
                                movementPage: movements.currentPage + 1,
                            },
                        })}
                    >
                        More movements
                    </Link>
                )}
            </nav>
        </section>
    );
}
