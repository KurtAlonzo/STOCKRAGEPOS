import { createElement, Fragment } from 'react';
import { renderToStaticMarkup } from 'react-dom/server';
import { expect, test, vi } from 'vite-plus/test';
import {
    mixedBasketFixture,
    catalogFixture,
} from '../../fixtures/pos/scenarios';
import AddonDialog from '../../components/pos/addon-dialog';
import Pos from '../../pages/pos/index';
import CartLines from '../../components/pos/cart-lines';

vi.mock('@inertiajs/react', () => ({
    usePage: () => ({ props: { auth: { user: { id: 1 } } } }),
    Head: () => null,
    Link: 'a',
}));
vi.mock('@/components/team-switcher', () => ({ TeamSwitcher: () => null }));
vi.mock('@/hooks/use-unsaved-changes', () => ({
    useUnsavedChanges: () => Object.assign(() => true, { dialog: null }),
}));
vi.mock('@/hooks/use-pos-quote', () => ({
    usePosQuote: () => ({
        status: 'current',
        error: null,
        quote: { ...mixedBasketFixture.quote, totalMinor: 9007199254740990 },
    }),
}));
vi.mock('@/layouts/pos-layout', () => ({
    default: (props: Record<string, unknown>) =>
        createElement(
            Fragment,
            null,
            ...['header', 'picker', 'cart', 'summary', 'footer'].map(
                (key) => props[key] as never,
            ),
        ),
}));
vi.mock('@/components/ui/dialog', () => ({
    Dialog: 'div',
    DialogContent: 'div',
    DialogTitle: 'h2',
    DialogDescription: 'p',
    DialogFooter: 'div',
}));

test('POS renders exact cents for large totals, tablet summary and product tiles', () => {
    const product = {
        ...catalogFixture[0],
        priceMinor: 9007199254740990,
        availability: { issues: [], stock: { balance: '2.000' } },
    };
    const html = renderToStaticMarkup(
        createElement(Pos, {
            business: mixedBasketFixture.quote.business,
            catalog: {
                data: [product],
                currentPage: 1,
                lastPage: 1,
                total: 1,
                perPage: 25,
            },
            addons: [],
            inventory: [],
            categories: [],
            filters: { query: '', categoryId: null },
            hasProducts: true,
            enabledPaymentMethods: [],
        } as never),
    );
    expect(html.match(/90071992547409\.90/g)).toHaveLength(3);
    expect(html).not.toContain('90071992547409.91');
});

test('add-on dialog renders exact cents and keeps removed selected add-ons available for removal', () => {
    const addon = {
        ...catalogFixture[2],
        priceMinor: 9007199254740987,
        availability: { issues: [], stock: null },
    };
    const html = renderToStaticMarkup(
        createElement(AddonDialog, {
            product: { ...catalogFixture[1], allowedAddonIds: [] },
            addons: [addon] as never,
            selected: [addon.id],
            apply: () => {},
            close: () => {},
        }),
    );
    expect(html).toContain('90071992547409.87');
    expect(html).toContain('Unavailable');
    expect(html).toContain('checked=""');
    expect(html).not.toContain('disabled=""');
});

test('cart retains modifier editing when the selected assignment is removed', () => {
    const line = mixedBasketFixture.quoteInput.lines[1];
    const html = renderToStaticMarkup(
        createElement(CartLines, {
            lines: [line],
            catalog: new Map([
                [line.productId, { ...catalogFixture[1], allowedAddonIds: [] }],
            ]),
            quote: null,
            buffers: {},
            errors: {},
            quantity: () => {},
            edit: () => {},
            remove: () => {},
        }),
    );
    expect(html).toContain('Edit add-ons');
});
