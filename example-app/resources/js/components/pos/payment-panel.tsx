import { formatPriceMinor } from '@/lib/catalog/form-values';
import type { PaymentInput } from '@/types/sales';
import type { PaymentForm } from '@/lib/pos/payment-input';
import { Input } from '@/components/ui/input';
export default function PaymentPanel({
    form,
    totalMinor,
    enabledMethods,
    errors,
    disabled,
    onChange,
}: {
    form: PaymentForm;
    totalMinor: number;
    enabledMethods: PaymentInput['method'][];
    errors: Record<string, string>;
    disabled: boolean;
    onChange: (form: PaymentForm) => void;
}) {
    return (
        <fieldset disabled={disabled} className="space-y-3">
            <legend className="font-semibold">Record payment</legend>
            <label className="block text-sm" htmlFor="payment-method">
                Method
            </label>
            <select
                id="payment-method"
                className="min-h-11 w-full rounded-md border bg-background px-3"
                value={form.method}
                onChange={(e) =>
                    onChange({
                        ...form,
                        method: e.target.value as PaymentInput['method'],
                        receivedConfirmed: false,
                    })
                }
            >
                {enabledMethods.map((m) => (
                    <option key={m} value={m}>
                        {m === 'cash'
                            ? 'Cash'
                            : m === 'gcash'
                              ? 'GCash'
                              : 'Maya'}
                    </option>
                ))}
            </select>
            {form.method === 'cash' ? (
                <>
                    <label htmlFor="cash-tender" className="block text-sm">
                        Cash tender (PHP)
                    </label>
                    <Input
                        id="cash-tender"
                        inputMode="decimal"
                        className="min-h-11"
                        value={form.tenderedText}
                        onChange={(e) =>
                            onChange({ ...form, tenderedText: e.target.value })
                        }
                    />
                </>
            ) : (
                <>
                    <p className="text-sm text-muted-foreground">
                        Check receipt of PHP {formatPriceMinor(totalMinor)}{' '}
                        outside this app. This records money already received.
                    </p>
                    <label
                        htmlFor="payment-reference"
                        className="block text-sm"
                    >
                        Reference (optional)
                    </label>
                    <Input
                        id="payment-reference"
                        className="min-h-11"
                        maxLength={255}
                        value={form.reference}
                        onChange={(e) =>
                            onChange({ ...form, reference: e.target.value })
                        }
                    />
                    <label className="flex min-h-11 items-center gap-3 text-sm">
                        <input
                            type="checkbox"
                            checked={form.receivedConfirmed}
                            onChange={(e) =>
                                onChange({
                                    ...form,
                                    receivedConfirmed: e.target.checked,
                                })
                            }
                        />
                        I checked receipt of the current total outside the app
                    </label>
                </>
            )}
            {Object.entries(errors).map(([key, error]) => (
                <p role="alert" key={key} className="text-sm text-destructive">
                    {error}
                </p>
            ))}
        </fieldset>
    );
}
