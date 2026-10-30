import { expect, test } from 'vite-plus/test';
import { managementFilters } from './management-filters';
test('query transport keeps active filters valid across later visits', () => {
    expect(
        managementFilters({ active: true, query: 'beef', kind: null }),
    ).toEqual({ active: '1', query: 'beef', kind: undefined });
    expect(managementFilters({ active: false, page: 2 })).toEqual({
        active: '0',
        page: 2,
    });
});
