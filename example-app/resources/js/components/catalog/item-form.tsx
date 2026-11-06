import { Link, useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useUnsavedChanges } from '@/hooks/use-unsaved-changes';
import { parsePriceMinor, formatPriceMinor } from '@/lib/catalog/form-values';
import { store, update, index } from '@/routes/products';
import { index as inventoryIndex } from '@/routes/inventory';
import { AvailabilityBadge } from './availability-badge';
import { RecipeEditor } from './recipe-editor';
import { AddonSelector } from './addon-selector';
import type { CatalogEditorProps, RecipeComponent } from '@/types/catalog';

export function ItemForm({
    business,
    item,
    categories,
    availability,
    inventory,
    addons,
}: CatalogEditorProps) {
    const form = useForm({
        name: item?.name ?? '',
        sku: item?.sku ?? '',
        kind: item?.kind ?? 'retail',
        stockBehavior:
            item && 'stockBehavior' in item ? item.stockBehavior : 'direct',
        categoryId:
            item?.categoryId === null || !item ? '' : String(item.categoryId),
        active: item?.active ?? true,
        price: item ? formatPriceMinor(item.priceMinor) : '',
        lowStockThreshold: '',
        components: (item && 'components' in item
            ? item.components
            : []) as RecipeComponent[],
        allowedAddonIds: item?.allowedAddonIds ?? [],
    });
    const serverErrors: Record<string, unknown> = form.errors;
    const mayClose = useUnsavedChanges(form.isDirty, form.processing);
    const direct =
        form.data.kind === 'retail' ||
        (form.data.kind === 'addon' && form.data.stockBehavior === 'direct');
    return (
        <form
            className="max-w-3xl space-y-5"
            onSubmit={(e) => {
                e.preventDefault();
                const priceMinor = parsePriceMinor(form.data.price);
                if (priceMinor === null) {
                    form.setError(
                        'price',
                        'Enter a PHP amount with at most two decimals.',
                    );
                    return;
                }
                mayClose.beforeSubmit();
                form.transform((data) => ({
                    name: data.name,
                    sku: data.sku.trim().toUpperCase(),
                    kind: data.kind,
                    categoryId: data.categoryId
                        ? Number(data.categoryId)
                        : null,
                    active: data.active,
                    priceMinor,
                    allowedAddonIds:
                        data.kind === 'addon' ? [] : data.allowedAddonIds,
                    ...(data.kind === 'addon'
                        ? { stockBehavior: data.stockBehavior }
                        : {}),
                    ...(!direct ? { components: data.components } : {}),
                    ...(item
                        ? { expectedRevision: item.revision }
                        : direct
                          ? {
                                lowStockThreshold:
                                    data.lowStockThreshold || null,
                            }
                          : {}),
                }));
                if (item)
                    form.patch(
                        update({
                            current_team: business.storeSlug,
                            product: item.id,
                        }).url,
                    );
                else form.post(store(business.storeSlug).url);
            }}
        >
            {mayClose.dialog}
            <div className="grid gap-4 sm:grid-cols-2">
                <label className="space-y-1">
                    Product name
                    <Input
                        aria-invalid={Boolean(serverErrors.name)}
                        aria-describedby={
                            serverErrors.name ? 'product-errors' : undefined
                        }
                        required
                        maxLength={255}
                        value={form.data.name}
                        onChange={(e) => form.setData('name', e.target.value)}
                    />
                </label>
                <label className="space-y-1">
                    SKU
                    <Input
                        aria-invalid={Boolean(serverErrors.sku)}
                        aria-describedby={
                            serverErrors.sku ? 'product-errors' : undefined
                        }
                        required
                        maxLength={64}
                        value={form.data.sku}
                        onChange={(e) => form.setData('sku', e.target.value)}
                    />
                    <span className="text-xs text-muted-foreground">
                        Saved in uppercase; unique within this store.
                    </span>
                </label>
                <label>
                    Type
                    <select
                        className="mt-1 w-full rounded-md border bg-background p-2"
                        disabled={!!item}
                        value={form.data.kind}
                        onChange={(e) =>
                            form.setData(
                                'kind',
                                e.target.value as typeof form.data.kind,
                            )
                        }
                    >
                        <option value="retail">Retail — direct stock</option>
                        <option value="food">Food — recipe</option>
                        <option value="addon">Add-on</option>
                    </select>
                </label>
                {form.data.kind === 'addon' && (
                    <label>
                        Stock behavior
                        <select
                            className="mt-1 w-full rounded-md border bg-background p-2"
                            disabled={!!item}
                            value={form.data.stockBehavior}
                            onChange={(e) =>
                                form.setData(
                                    'stockBehavior',
                                    e.target.value as 'direct' | 'recipe',
                                )
                            }
                        >
                            <option value="direct">Direct stock</option>
                            <option value="recipe">
                                Ingredient/supply recipe
                            </option>
                        </select>
                    </label>
                )}
                <label>
                    Category
                    <select
                        className="mt-1 w-full rounded-md border bg-background p-2"
                        value={form.data.categoryId}
                        onChange={(e) =>
                            form.setData('categoryId', e.target.value)
                        }
                    >
                        <option value="">Uncategorized</option>
                        {categories.map((c) => (
                            <option key={c.id} value={c.id}>
                                {c.name}
                                {!c.active ? ' (inactive)' : ''}
                            </option>
                        ))}
                    </select>
                </label>
                <label className="space-y-1">
                    Selling price (PHP)
                    <Input
                        aria-invalid={Boolean(
                            serverErrors.price || serverErrors.priceMinor,
                        )}
                        aria-describedby={
                            serverErrors.price || serverErrors.priceMinor
                                ? 'product-errors'
                                : undefined
                        }
                        required
                        inputMode="decimal"
                        value={form.data.price}
                        onChange={(e) => form.setData('price', e.target.value)}
                    />
                </label>
                {!item && direct && (
                    <label className="space-y-1">
                        Low-stock threshold (each, optional)
                        <Input
                            inputMode="numeric"
                            value={form.data.lowStockThreshold}
                            onChange={(e) =>
                                form.setData(
                                    'lowStockThreshold',
                                    e.target.value,
                                )
                            }
                        />
                    </label>
                )}
            </div>
            {!direct && (
                <RecipeEditor
                    errors={serverErrors}
                    components={form.data.components}
                    inventory={inventory}
                    change={(rows) => form.setData('components', rows)}
                />
            )}
            {form.data.kind !== 'addon' && (
                <AddonSelector
                    addons={addons}
                    selected={form.data.allowedAddonIds}
                    change={(ids) => form.setData('allowedAddonIds', ids)}
                />
            )}
            <label className="flex gap-2">
                <input
                    type="checkbox"
                    checked={form.data.active}
                    onChange={(e) => form.setData('active', e.target.checked)}
                />
                Active product
            </label>
            {availability && <AvailabilityBadge value={availability} />}
            {item && 'stockItemId' in item && (
                <Link
                    className="text-sm underline"
                    href={inventoryIndex(business.storeSlug, {
                        query: { itemId: item.stockItemId },
                    })}
                >
                    Receive stock, correct quantities or edit threshold
                </Link>
            )}
            <div
                id="product-errors"
                role="alert"
                className="space-y-1 text-sm text-destructive"
            >
                {Object.entries(form.errors).map(([key, error]) => (
                    <p key={key}>
                        {key}: {error}
                    </p>
                ))}
            </div>
            <div className="flex flex-wrap gap-2">
                <Button disabled={form.processing}>
                    {form.processing ? 'Saving…' : 'Save product'}
                </Button>
                <Button variant="outline" asChild>
                    <Link href={index(business.storeSlug)}>
                        Back to Catalog
                    </Link>
                </Button>
                {'expectedRevision' in form.errors && (
                    <Button
                        variant="outline"
                        type="button"
                        onClick={() => window.location.reload()}
                    >
                        Reload current version
                    </Button>
                )}
            </div>
        </form>
    );
}
