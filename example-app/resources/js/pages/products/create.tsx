import { Head } from '@inertiajs/react';
import { ItemForm } from '@/components/catalog/item-form';
import type { CatalogEditorProps } from '@/types/catalog';
export default function CreateProduct(props: CatalogEditorProps) {
    return (
        <div className="space-y-6 p-4 md:p-6">
            <Head title="Add product" />
            <h1 className="text-2xl font-semibold">Add product</h1>
            <ItemForm {...props} />
        </div>
    );
}
