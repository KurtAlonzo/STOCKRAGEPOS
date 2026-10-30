import { Link } from '@inertiajs/react';
export default function CheckoutScreenNotice({
    dashboardHref,
}: {
    dashboardHref: string;
}) {
    return (
        <div className="space-y-4 p-8">
            <h1 className="text-2xl font-semibold">
                Checkout requires a larger screen
            </h1>
            <p>
                Use a tablet or desktop with a browser window at least 768
                pixels wide. You can still manage your store here.
            </p>
            <Link href={dashboardHref}>Return to dashboard</Link>
        </div>
    );
}
