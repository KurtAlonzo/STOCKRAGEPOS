import { expect, it } from 'vite-plus/test';
import { clearPosMemory, subscribePosMemory } from './auth-scope';
it('invalidates only the original actor memory without deleting storage', () => {
    let a = 0,
        b = 0;
    const stopA = subscribePosMemory(1, () => a++),
        stopB = subscribePosMemory(2, () => b++);
    clearPosMemory(1);
    expect(a).toBe(1);
    expect(b).toBe(0);
    stopA();
    stopB();
});

import { createAuthScope } from './auth-scope';
it('late focus verification cannot re-enable a scope invalidated by logout', () => {
    const scope = createAuthScope();
    const pending = scope.capture();
    expect(scope.accepts(pending)).toBe(true);
    scope.invalidate();
    expect(scope.accepts(pending)).toBe(false);
    expect(scope.accepts(scope.capture())).toBe(false);
});
