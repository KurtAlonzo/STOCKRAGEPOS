import { Head, Link } from '@inertiajs/react';
import { edit } from '@/routes/profile';
export default function NoStoreAccess() {
    return (
        <div className="p-8">
            <Head title="Store access unavailable" />
            <h1 className="text-2xl font-semibold">Store access unavailable</h1>
            <p className="my-4">
                Contact your administrator to restore access to an owned store.
            </p>
            <Link href={edit()}>Account settings</Link>
        </div>
    );
}
