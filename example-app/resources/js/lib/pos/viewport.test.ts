import { expect, test } from 'vite-plus/test';
import { getCheckoutViewport } from './viewport';
test('boundaries', () => {
    expect([767, 768, 1023, 1024].map(getCheckoutViewport)).toEqual([
        'unsupported',
        'portrait',
        'portrait',
        'wide',
    ]);
});
