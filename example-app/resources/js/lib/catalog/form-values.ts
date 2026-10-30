export function parsePriceMinor(text: string): number | null {
    if (!/^\d+(\.\d{1,2})?$/.test(text)) return null;
    const [whole, fraction = ''] = text.split('.');
    const scaled = BigInt(whole) * 100n + BigInt(fraction.padEnd(2, '0'));
    return scaled <= BigInt(Number.MAX_SAFE_INTEGER) ? Number(scaled) : null;
}
export function formatPriceMinor(value: number): string {
    const scaled = BigInt(value);
    return `${scaled / 100n}.${String(scaled % 100n).padStart(2, '0')}`;
}
export function projectQuantity(
    balance: string,
    delta: string,
    unit: 'each' | 'g' | 'ml',
): string | null {
    function scaled(value: string): bigint | null {
        const match = /^(-?)(\d+)(?:\.(\d{1,3}))?$/.exec(value);
        if (!match) return null;
        const amount =
            BigInt(match[2]) * 1000n + BigInt((match[3] ?? '').padEnd(3, '0'));
        if (
            amount > BigInt(Number.MAX_SAFE_INTEGER) ||
            (unit === 'each' && amount % 1000n !== 0n)
        )
            return null;
        return match[1] ? -amount : amount;
    }
    const before = scaled(balance),
        change = scaled(delta);
    if (before === null || change === null) return null;
    const after = before + change,
        magnitude = after < 0n ? -after : after;
    if (magnitude > BigInt(Number.MAX_SAFE_INTEGER)) return null;
    return `${after < 0n ? '-' : ''}${magnitude / 1000n}.${String(magnitude % 1000n).padStart(3, '0')}`;
}
