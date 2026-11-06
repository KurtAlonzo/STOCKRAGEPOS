import type { ReactNode } from 'react';
import { usePage } from '@inertiajs/react';
import { dashboard } from '@/routes';
import { useCheckoutViewport } from '@/hooks/use-checkout-viewport';
import CheckoutScreenNotice from '@/components/pos/checkout-screen-notice';
import {
    Sheet,
    SheetContent,
    SheetTrigger,
    SheetTitle,
} from '@/components/ui/sheet';
import { Button } from '@/components/ui/button';
export default function PosLayout({
    picker,
    cart,
    summary,
    footer,
    header,
}: {
    picker: ReactNode;
    cart: ReactNode;
    summary: ReactNode;
    footer: ReactNode;
    header?: ReactNode;
}) {
    const viewport = useCheckoutViewport();
    const { currentTeam } = usePage().props;
    if (viewport === 'pending')
        return (
            <div aria-busy="true" className="p-8">
                Loading layout…
            </div>
        );
    if (viewport === 'unsupported')
        return (
            <CheckoutScreenNotice
                dashboardHref={
                    currentTeam ? dashboard(currentTeam.slug).url : '/dashboard'
                }
            />
        );
    return (
        <div className="flex h-dvh flex-col">
            <header className="shrink-0 border-b p-4">
                {header ?? 'STOCKRAGE · Development preview — sample data'}
            </header>
            <div className="grid min-h-0 flex-1 lg:grid-cols-[1fr_24rem]">
                <main className="min-w-0 overflow-y-auto p-6">{picker}</main>
                {viewport === 'wide' ? (
                    <aside className="flex min-h-0 flex-col border-l">
                        <div className="min-h-0 flex-1 overflow-y-auto p-6">
                            {cart}
                        </div>
                        <footer className="border-t p-6">{footer}</footer>
                    </aside>
                ) : (
                    <Sheet>
                        <SheetTrigger asChild>
                            <Button className="m-4 min-h-11">{summary}</Button>
                        </SheetTrigger>
                        <SheetContent className="flex w-full flex-col p-6 sm:max-w-lg [&>button]:min-h-11 [&>button]:min-w-11">
                            <SheetTitle>
                                {header ? 'Cart' : 'Cart preview'}
                            </SheetTitle>
                            <div className="min-h-0 flex-1 overflow-y-auto">
                                {cart}
                            </div>
                            <footer>{footer}</footer>
                        </SheetContent>
                    </Sheet>
                )}
            </div>
        </div>
    );
}
