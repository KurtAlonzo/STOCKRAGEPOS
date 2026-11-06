const listeners = new Map<number, Set<() => void>>();
let channel: BroadcastChannel | null = null;
function local(userId: number) {
    listeners.get(userId)?.forEach((l) => l());
}
function connect() {
    if (
        typeof window !== 'undefined' &&
        typeof BroadcastChannel !== 'undefined' &&
        !channel
    ) {
        channel = new BroadcastChannel('stockrage-pos-auth');
        channel.onmessage = (e) => {
            if (typeof e.data === 'number') local(e.data);
        };
    }
}
export function clearPosMemory(userId: number): void {
    connect();
    local(userId);
    channel?.postMessage(userId);
}
export function subscribePosMemory(userId: number, listener: () => void) {
    connect();
    const set = listeners.get(userId) ?? new Set<() => void>();
    set.add(listener);
    listeners.set(userId, set);
    return () => {
        set.delete(listener);
    };
}

export function createAuthScope() {
    let generation = 0,
        invalidated = false;
    return {
        capture: () => ++generation,
        accepts: (candidate: number) =>
            !invalidated && candidate === generation,
        invalidate: () => {
            invalidated = true;
            generation++;
        },
    };
}
