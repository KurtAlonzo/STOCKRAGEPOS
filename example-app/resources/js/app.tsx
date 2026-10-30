import { createInertiaApp } from '@inertiajs/react';
import { Toaster } from '@/components/ui/sonner';
import { TooltipProvider } from '@/components/ui/tooltip';
import { initializeTheme } from '@/hooks/use-appearance';
import AppLayout from '@/layouts/app-layout';
import AuthLayout from '@/layouts/auth-layout';
import PosLayout from '@/layouts/pos-layout';
import SettingsLayout from '@/layouts/settings/layout';

const appName = import.meta.env.VITE_APP_NAME || 'STOCKRAGE POS';

void createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    layout: (name) => {
        switch (true) {
            case name === 'dev/pos-shell':
                return ({ children }: { children: React.ReactNode }) => (
                    <PosLayout
                        picker={children}
                        cart={<p>Example cart — sample data only</p>}
                        summary={<span>View example cart</span>}
                        footer={<p>Checkout is unavailable in this preview.</p>}
                    />
                );
            case name.startsWith('auth/'):
                return AuthLayout;
            case name.startsWith('settings/'):
            case name.startsWith('teams/'):
                return [AppLayout, SettingsLayout];
            default:
                return AppLayout;
        }
    },
    strictMode: true,
    withApp(app) {
        return (
            <TooltipProvider delayDuration={0}>
                {app}
                <Toaster />
            </TooltipProvider>
        );
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on load...
initializeTheme();
