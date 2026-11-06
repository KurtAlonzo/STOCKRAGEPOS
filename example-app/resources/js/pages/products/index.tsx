import { managementFilters } from '@/lib/management-filters';
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { AvailabilityBadge } from '@/components/catalog/availability-badge';
import { formatPriceMinor } from '@/lib/catalog/form-values';
import { index, create, edit } from '@/routes/products';
import { index as categoriesIndex } from '@/routes/categories';
import type { CatalogIndexProps } from '@/types/catalog';
export default function Products({
    business,
    catalog,
    categories,
    filters,
}: CatalogIndexProps) {
    const [query, setQuery] = useState(filters.query);
    const slug = business.storeSlug;
    function filter(key: string, value: string) {
        router.get(
            index(slug).url,
            {
                ...managementFilters(filters),
                [key]: value || undefined,
                page: 1,
            },
            { preserveState: true, replace: true },
        );
    }
    return (
        <div className="space-y-6 p-4 md:p-6">
            <Head title="Catalog" />
            <div className="flex flex-wrap justify-between gap-3">
                <div>
                    <h1 className="text-2xl font-semibold">Catalog</h1>
                    <p className="text-sm text-muted-foreground">
                        Sellable retail, food and add-ons.
                    </p>
                </div>
                <div className="flex gap-2">
                    <Button variant="outline" asChild>
                        <Link href={categoriesIndex(slug)}>Categories</Link>
                    </Button>
                    <Button asChild>
                        <Link href={create(slug)}>Add product</Link>
                    </Button>
                </div>
            </div>
            <form
                className="flex flex-wrap gap-2"
                onSubmit={(e) => {
                    e.preventDefault();
                    filter('query', query);
                }}
            >
                <Input
                    className="w-full sm:w-64"
                    aria-label="Search name or SKU"
                    placeholder="Search name or SKU"
                    value={query}
                    onChange={(e) => setQuery(e.target.value)}
                />
                <Button variant="outline">Search</Button>
                <select
                    className="rounded-md border bg-background p-2"
                    aria-label="Product type"
                    value={filters.kind ?? ''}
                    onChange={(e) => filter('kind', e.target.value)}
                >
                    <option value="">All types</option>
                    <option value="retail">Retail</option>
                    <option value="food">Food</option>
                    <option value="addon">Add-on</option>
                </select>
                <select
                    className="max-w-full rounded-md border bg-background p-2"
                    aria-label="Category"
                    value={filters.categoryId ?? ''}
                    onChange={(e) => filter('categoryId', e.target.value)}
                >
                    <option value="">All categories</option>
                    {categories.map((c) => (
                        <option key={c.id} value={c.id}>
                            {c.name}
                        </option>
                    ))}
                </select>
                <select
                    className="rounded-md border bg-background p-2"
                    aria-label="Active state"
                    value={
                        filters.active === null
                            ? ''
                            : filters.active
                              ? '1'
                              : '0'
                    }
                    onChange={(e) => filter('active', e.target.value)}
                >
                    <option value="">All products</option>
                    <option value="1">Active</option>
                    <option value="0">Inactive</option>
                </select>
            </form>
            {!catalog.data.length && (
                <p className="rounded-md border p-8 text-muted-foreground">
                    {filters.query ||
                    filters.kind ||
                    filters.categoryId ||
                    filters.active !== null
                        ? 'No products match these filters.'
                        : 'No products yet. Add your first retail or recipe product.'}
                </p>
            )}
            <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                {catalog.data.map((item) => (
                    <article
                        key={item.id}
                        className="min-w-0 space-y-3 rounded-md border p-4"
                    >
                        <div className="flex flex-wrap justify-between gap-2">
                            <h2 className="font-semibold break-words">
                                {item.name}
                            </h2>
                            <span>₱{formatPriceMinor(item.priceMinor)}</span>
                        </div>
                        <p className="text-sm break-all text-muted-foreground">
                            {item.sku} · {item.kind}
                        </p>
                        <AvailabilityBadge value={item.availability} />
                        <Button variant="outline" size="sm" asChild>
                            <Link
                                href={edit({
                                    current_team: slug,
                                    product: item.id,
                                })}
                            >
                                Edit product
                            </Link>
                        </Button>
                    </article>
                ))}
            </div>
            <div className="flex items-center gap-3">
                <Button
                    variant="outline"
                    disabled={catalog.currentPage <= 1}
                    onClick={() =>
                        router.get(index(slug).url, {
                            ...managementFilters(filters),
                            page: catalog.currentPage - 1,
                        })
                    }
                >
                    Previous
                </Button>
                <span className="text-sm">
                    Page {catalog.currentPage} of {catalog.lastPage}
                </span>
                <Button
                    variant="outline"
                    disabled={catalog.currentPage >= catalog.lastPage}
                    onClick={() =>
                        router.get(index(slug).url, {
                            ...managementFilters(filters),
                            page: catalog.currentPage + 1,
                        })
                    }
                >
                    Next
                </Button>
            </div>
        </div>
    );
}
