import { Head } from '@inertiajs/react';
import { ItemForm } from '@/components/catalog/item-form';
import type { CatalogEditorProps } from '@/types/catalog';
export default function EditProduct(props: CatalogEditorProps) {
    return (
        <div className="space-y-6 p-4 md:p-6">
            <Head title="Edit product" />
            <h1 className="text-2xl font-semibold">Edit product</h1>
            <ItemForm
                key={`${props.business.storeId}-${props.item?.id}-${props.item?.revision}`}
                {...props}
            />
        </div>
    );
}
