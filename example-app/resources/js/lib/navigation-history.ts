export function historyIndex(state: unknown): number | null {
    return state !== null &&
        typeof state === 'object' &&
        'stockrageHistoryIndex' in state &&
        typeof state.stockrageHistoryIndex === 'number'
        ? state.stockrageHistoryIndex
        : null;
}
export function historyState(state: unknown, index: number) {
    return {
        ...(state !== null && typeof state === 'object' ? state : {}),
        stockrageHistoryIndex: index,
    };
}
let installed = false;
const guards = new Set<(event: PopStateEvent) => void>();
export function registerHistoryGuard(guard: (event: PopStateEvent) => void) {
    guards.add(guard);
    return () => {
        guards.delete(guard);
    };
}
export function installHistoryTracking() {
    if (typeof window === 'undefined' || installed) return;
    installed = true;
    // Register before Inertia initializes so its popstate handler cannot replace the editor first.
    window.addEventListener(
        'popstate',
        (event) => {
            for (const guard of guards) guard(event);
        },
        true,
    );
    const push = window.history.pushState.bind(window.history),
        replace = window.history.replaceState.bind(window.history);
    replace(
        historyState(
            window.history.state,
            historyIndex(window.history.state) ?? 0,
        ),
        '',
    );
    window.history.pushState = (
        state: unknown,
        unused: string,
        url?: string | URL | null,
    ) =>
        push(
            historyState(state, (historyIndex(window.history.state) ?? 0) + 1),
            unused,
            url,
        );
    window.history.replaceState = (
        state: unknown,
        unused: string,
        url?: string | URL | null,
    ) =>
        replace(
            historyState(state, historyIndex(window.history.state) ?? 0),
            unused,
            url,
        );
}
