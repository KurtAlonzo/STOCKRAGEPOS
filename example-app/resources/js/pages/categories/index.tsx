import { Head, Link, useForm } from '@inertiajs/react';
import { useState } from 'react';
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
import { store, update, index } from '@/routes/categories';
import { index as catalogIndex } from '@/routes/products';
import type { Category, CategoryIndexProps } from '@/types/catalog';
function CategoryForm({
    slug,
    item,
    close,
}: {
    slug: string;
    item: Category | null;
    close: () => void;
}) {
    const form = useForm({
        name: item?.name ?? '',
        active: item?.active ?? true,
    });
    const serverErrors: Record<string, unknown> = form.errors;
    const mayClose = useUnsavedChanges(form.isDirty, form.processing);
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
                        {item ? 'Edit category' : 'Add category'}
                    </DialogTitle>
                    <DialogDescription>
                        Categories organize the catalog. Active products must be
                        reassigned before their category is deactivated.
                    </DialogDescription>
                </DialogHeader>
                <form
                    className="space-y-4"
                    onSubmit={(e) => {
                        e.preventDefault();
                        mayClose.beforeSubmit();
                        form.transform((data) => ({
                            ...data,
                            ...(item
                                ? { expectedRevision: item.revision }
                                : {}),
                        }));
                        if (item)
                            form.patch(
                                update({
                                    current_team: slug,
                                    category: item.id,
                                }).url,
                                { onSuccess: close },
                            );
                        else form.post(store(slug).url, { onSuccess: close });
                    }}
                >
                    <label className="block">
                        Name
                        <Input
                            aria-invalid={Boolean(serverErrors.name)}
                            aria-describedby={
                                serverErrors.name
                                    ? 'category-errors'
                                    : undefined
                            }
                            required
                            maxLength={100}
                            value={form.data.name}
                            onChange={(e) =>
                                form.setData('name', e.target.value)
                            }
                        />
                    </label>
                    <label className="flex gap-2">
                        <input
                            type="checkbox"
                            checked={form.data.active}
                            onChange={(e) =>
                                form.setData('active', e.target.checked)
                            }
                        />
                        Active
                    </label>
                    <div
                        id="category-errors"
                        role="alert"
                        className="text-sm text-destructive"
                    >
                        {Object.entries(form.errors).map(([key, error]) => (
                            <p key={key}>{error}</p>
                        ))}
                    </div>
                    <Button disabled={form.processing}>Save category</Button>
                </form>
            </DialogContent>
        </Dialog>
    );
}
export default function Categories({
    business,
    categories,
}: CategoryIndexProps) {
    const [editing, setEditing] = useState<Category | null | undefined>(
        undefined,
    );
    return (
        <div className="space-y-6 p-4 md:p-6">
            <Head title="Categories" />
            <Link
                className="text-sm underline"
                href={catalogIndex(business.storeSlug)}
            >
                Back to Catalog
            </Link>
            <div className="flex flex-wrap justify-between gap-3">
                <h1 className="text-2xl font-semibold">Categories</h1>
                <Button onClick={() => setEditing(null)}>Add category</Button>
            </div>
            {!categories.data.length && (
                <p>No categories yet. Products can also be uncategorized.</p>
            )}
            {categories.data.map((c) => (
                <article
                    className="flex flex-wrap items-center justify-between gap-3 rounded-md border p-4"
                    key={c.id}
                >
                    <span className="break-words">
                        {c.name} · {c.active ? 'Active' : 'Inactive'}
                    </span>
                    <Button variant="outline" onClick={() => setEditing(c)}>
                        Edit
                    </Button>
                </article>
            ))}
            <nav className="flex gap-3">
                {categories.currentPage > 1 && (
                    <Link
                        href={index(business.storeSlug, {
                            query: { page: categories.currentPage - 1 },
                        })}
                    >
                        Previous
                    </Link>
                )}
                {categories.currentPage < categories.lastPage && (
                    <Link
                        href={index(business.storeSlug, {
                            query: { page: categories.currentPage + 1 },
                        })}
                    >
                        Next
                    </Link>
                )}
            </nav>
            {editing !== undefined && (
                <CategoryForm
                    slug={business.storeSlug}
                    item={editing}
                    close={() => setEditing(undefined)}
                />
            )}
        </div>
    );
}
