import { usePage } from '@inertiajs/react';

export default function AppLogo() {
    const { name } = usePage().props;

    return (
        <>
            <div className="flex aspect-square size-8 items-center justify-center rounded-md bg-transparent">
                <img
                    src="/images/stockrage-logo.png"
                    alt="STOCKRAGE"
                    className="size-full object-contain"
                />
            </div>
            <div className="ml-1 grid flex-1 text-left text-sm">
                <span className="mb-0.5 truncate leading-tight font-semibold">
                    {name || 'STOCKRAGE POS'}
                </span>
            </div>
        </>
    );
}
