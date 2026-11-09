import { Link } from '@inertiajs/react';
import { index } from '@/routes/inventory';
import type { StockMovement } from '@/types/inventory';

export default function StockMovements({
    movements,
    storeSlug,
}: {
    movements: StockMovement[];
    storeSlug: string;
}) {
    return (
        <div className="space-y-3">
            {movements.map((movement) => (
                <article
                    key={movement.id}
                    className="space-y-1 rounded-md border p-3 text-sm"
                >
                    <Link
                        className="inline-block min-h-11 content-center font-medium underline"
                        href={index(storeSlug, {
                            query: { itemId: movement.inventoryItemId },
                        })}
                    >
                        {movement.itemName}
                    </Link>
                    <p className="break-words tabular-nums">
                        {movement.before} → {movement.after} {movement.unit} (
                        {movement.delta})
                    </p>
                    <p className="break-words text-muted-foreground">
                        {movement.actorName} · {movement.reason} ·{' '}
                        {new Date(movement.occurredAt).toLocaleString('en-PH', {
                            timeZone: 'Asia/Manila',
                        })}
                    </p>
                </article>
            ))}
        </div>
    );
}
