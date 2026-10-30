import { expect, test } from 'vite-plus/test';
import { matchesNavigationPath } from './navigation-path';
test('path segments', () => {
    expect(matchesNavigationPath('/s/products/42?x=y', '/s/products')).toBe(
        true,
    );
    expect(matchesNavigationPath('/s/products-old', '/s/products')).toBe(false);
});
