import { useSyncExternalStore } from 'react';
import { getCheckoutViewport } from '@/lib/pos/viewport';
const subscribe = (callback: () => void) => {
    window.addEventListener('resize', callback);
    return () => window.removeEventListener('resize', callback);
};
export function useCheckoutViewport() {
    return useSyncExternalStore(
        subscribe,
        () => getCheckoutViewport(window.innerWidth),
        () => 'pending' as const,
    );
}
