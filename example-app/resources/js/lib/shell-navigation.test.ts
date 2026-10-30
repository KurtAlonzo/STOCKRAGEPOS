import { expect, test } from 'vite-plus/test';
import { getShellNavItems } from './shell-navigation';
import { getActiveNavIndex } from './navigation-path';
test('only exposes working owner destinations', () => {
    expect(getShellNavItems(null).map((item) => item.title)).toEqual([
        'Settings',
    ]);
    expect(
        getShellNavItems({
            id: 1,
            name: 'Store',
            slug: 'store',
            role: 'owner',
            isPersonal: true,
        }).map((item) => item.title),
    ).toEqual(['Dashboard', 'Settings']);
});
test('selects the most specific destination', () => {
    expect(
        getActiveNavIndex('/s/inventory/history', [
            { title: 'Inventory', href: '/s/inventory' },
            { title: 'Stock History', href: '/s/inventory/history' },
        ]),
    ).toBe(1);
    expect(
        getActiveNavIndex('/settings/teams/store', getShellNavItems(null)),
    ).toBe(0);
});
