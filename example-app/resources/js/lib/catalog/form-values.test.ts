import { expect, test } from 'vite-plus/test';
import {
    parsePriceMinor,
    formatPriceMinor,
    projectQuantity,
} from './form-values';
test('exact money parsing and range boundaries', () => {
    expect(parsePriceMinor('150.25')).toBe(15025);
    expect(parsePriceMinor('0')).toBe(0);
    expect(parsePriceMinor('1.001')).toBeNull();
    expect(parsePriceMinor('90071992547409.92')).toBeNull();
    expect(parsePriceMinor(formatPriceMinor(9007199254740991))).toBe(
        9007199254740991,
    );
});
test('stock previews keep exact thousandths at the safe integer boundary', () => {
    expect(projectQuantity('9007199254740.990', '0.001', 'g')).toBe(
        '9007199254740.991',
    );
    expect(projectQuantity('9007199254740.991', '0.001', 'g')).toBeNull();
    expect(projectQuantity('0.125', '-0.500', 'g')).toBe('-0.375');
    expect(projectQuantity('2.000', '0.5', 'each')).toBeNull();
});
