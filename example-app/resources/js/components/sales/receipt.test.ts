import { expect, it } from 'vite-plus/test';
import { createElement } from 'react';
import { renderToStaticMarkup } from 'react-dom/server';
import Receipt from './receipt';
import { mixedBasketFixture } from '@/fixtures/pos/scenarios';
it('receipt preserves original tender, change and modifiers without including internal audit notes', () => {
    const sale = {
        ...mixedBasketFixture.sale,
        reversal: {
            id: 1,
            saleId: 1,
            idempotencyKey: 'aaaaaaaa-1111-4111-8111-111111111111',
            totalMinor: 41000,
            reason: 'INTERNAL-AUDIT-ONLY',
            refundConfirmed: true as const,
            refundMethod: 'maya' as const,
            refundReference: null,
            restock: [],
            actorId: 1,
            actorName: 'Owner',
            occurredAt: '2026-11-09T00:00:00Z',
        },
    };
    const html = renderToStaticMarkup(
        createElement(Receipt, { sale, storeName: 'Current Store' }),
    );
    expect(html).toContain('Current Store');
    expect(html).toContain('Cash tender');
    expect(html).toContain('500.00');
    expect(html).toContain('90.00');
    expect(html).toContain('410.00');
    expect(html).toContain('Recorded refund');
    expect(html).toContain('maya');
    expect(html).toContain('Cheese');
    expect(html).not.toContain('INTERNAL-AUDIT-ONLY');
});
it('an original receipt never invents a refund or tax amount', () => {
    const html = renderToStaticMarkup(
        createElement(Receipt, {
            sale: mixedBasketFixture.sale,
            storeName: 'Store',
        }),
    );
    expect(html).not.toContain('Recorded refund');
    expect(html).not.toContain('VAT');
});
