import { expect, test } from 'vite-plus/test';
import { historyIndex, historyState } from './navigation-history';
test('history markers preserve Inertia page state and distinguish traversal direction', () => {
    const page = { page: 'encrypted-page', scrollRegions: [] };
    expect(historyState(page, 4)).toEqual({
        ...page,
        stockrageHistoryIndex: 4,
    });
    expect(historyIndex(historyState(page, 4))).toBe(4);
    expect(historyIndex(null)).toBeNull();
    expect(historyIndex(historyState(page, 2))! - 4).toBe(-2);
});
