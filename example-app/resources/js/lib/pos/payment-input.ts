import type { PaymentInput } from '@/types/sales';
export type PaymentForm = {
    method: PaymentInput['method'];
    tenderedText: string;
    reference: string;
    receivedConfirmed: boolean;
};
export const emptyPayment: PaymentForm = {
    method: 'cash',
    tenderedText: '',
    reference: '',
    receivedConfirmed: false,
};
export function parsePayment(
    form: PaymentForm,
    totalMinor: number,
    enabledMethods: PaymentInput['method'][],
): { payment: PaymentInput | null; errors: Record<string, string> } {
    const invalid = (field: string, message: string) => ({
        payment: null,
        errors: { [field]: message },
    });
    if (!enabledMethods.includes(form.method))
        return invalid('method', 'Choose an enabled payment method.');
    if (form.method === 'cash') {
        if (!/^\d+(\.\d{1,2})?$/.test(form.tenderedText))
            return invalid(
                'tenderedText',
                'Enter a cash amount with up to two decimal places.',
            );
        const [whole, fraction = ''] = form.tenderedText.split('.');
        const value = BigInt(whole) * 100n + BigInt(fraction.padEnd(2, '0'));
        if (value > BigInt(Number.MAX_SAFE_INTEGER))
            return invalid(
                'tenderedText',
                'Amount exceeds the supported range.',
            );
        if (value < BigInt(totalMinor))
            return invalid('tenderedText', 'Cash tender must cover the total.');
        return {
            payment: { method: 'cash', tenderedMinor: Number(value) },
            errors: {},
        };
    }
    if (!form.receivedConfirmed)
        return invalid(
            'receivedConfirmed',
            'Check receipt outside the app and confirm the current total.',
        );
    if (form.reference.trim().length > 255)
        return invalid(
            'reference',
            'Reference must be at most 255 characters.',
        );
    return {
        payment: {
            method: form.method,
            receivedConfirmed: true,
            reference: form.reference.trim() || null,
        },
        errors: {},
    };
}
