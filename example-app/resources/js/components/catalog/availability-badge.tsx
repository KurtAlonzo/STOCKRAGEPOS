import type { CatalogAvailability } from '@/types/catalog';
export function AvailabilityBadge({ value }: { value: CatalogAvailability }) {
    const labels = {
        inactive: 'Inactive',
        available: 'Available',
        warning: 'Stock warning',
        blocked: 'Unavailable',
    };
    return (
        <div className="space-y-1 text-sm">
            <span
                className={
                    value.status === 'blocked'
                        ? 'font-medium text-destructive'
                        : 'font-medium'
                }
            >
                {labels[value.status]}
            </span>
            {value.stock && (
                <p className="text-muted-foreground">
                    {value.stock.balance} {value.stock.unit}
                </p>
            )}
            {value.issues.map((issue) => (
                <p key={issue.id} className="text-muted-foreground">
                    {issue.message}
                </p>
            ))}
        </div>
    );
}
