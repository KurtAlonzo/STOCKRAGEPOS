import { expect, it } from 'vite-plus/test';
import { parsePayment } from './payment-input';
it('parses cash in exact centavos and rejects unfinished, insufficient or overflowing tender', () => {
    for (const [text, want] of [
        ['500.00', 50000],
        ['410', 41000],
    ] as const)
        expect(
            parsePayment(
                {
                    method: 'cash',
                    tenderedText: text,
                    reference: '',
                    receivedConfirmed: false,
                },
                41000,
                ['cash'],
            ).payment,
        ).toEqual({ method: 'cash', tenderedMinor: want });
    for (const text of [
        '',
        '.',
        '500.',
        '400.00',
        '500.001',
        '90071992547409.92',
    ])
        expect(
            parsePayment(
                {
                    method: 'cash',
                    tenderedText: text,
                    reference: '',
                    receivedConfirmed: false,
                },
                41000,
                ['cash'],
            ).payment,
        ).toBeNull();
});
it('requires checked external receipt and keeps references optional', () => {
    expect(
        parsePayment(
            {
                method: 'gcash',
                tenderedText: '',
                reference: '  ',
                receivedConfirmed: true,
            },
            41000,
            ['gcash'],
        ).payment,
    ).toEqual({ method: 'gcash', receivedConfirmed: true, reference: null });
    expect(
        parsePayment(
            {
                method: 'maya',
                tenderedText: '',
                reference: ' abc ',
                receivedConfirmed: false,
            },
            41000,
            ['maya'],
        ).payment,
    ).toBeNull();
});
