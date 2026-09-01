import { describe, expect, test } from 'vitest';
import RefundCompositionFormatter from '../../../../src/module/mollie-payments/components/mollie-refund-manager/services/RefundCompositionFormatter';

const formatter = new RefundCompositionFormatter();
const NO_COMPOSITION = 'No composition';

describe('RefundCompositionFormatter.format', () => {
    test('returns the fallback label if no metadata exists', () => {
        expect(formatter.format({}, '€', NO_COMPOSITION)).toEqual([NO_COMPOSITION]);
    });

    test('returns the fallback label if the composition is empty', () => {
        expect(formatter.format({ metadata: { composition: [] } }, '€', NO_COMPOSITION)).toEqual([NO_COMPOSITION]);
    });

    test('formats an entry with quantity, amount and currency', () => {
        const refund = {
            metadata: {
                composition: [{ label: 'Product 1', swReference: '', quantity: 2, amount: 10 }],
            },
        };

        expect(formatter.format(refund, '€', NO_COMPOSITION)).toEqual(['Product 1 (2 x 10 €)']);
    });

    test('prefers the shopware reference over the label if present', () => {
        const refund = {
            metadata: {
                composition: [{ label: 'Product 1', swReference: 'SW-REF-1', quantity: 1, amount: 5 }],
            },
        };

        expect(formatter.format(refund, '€', NO_COMPOSITION)).toEqual(['SW-REF-1 (1 x 5 €)']);
    });

    test('omits the quantity for entries with quantity 0', () => {
        const refund = {
            metadata: {
                composition: [{ label: 'Partial refund', swReference: '', quantity: 0, amount: 5 }],
            },
        };

        expect(formatter.format(refund, '€', NO_COMPOSITION)).toEqual(['Partial refund (5 €)']);
    });

    test('formats multiple entries', () => {
        const refund = {
            metadata: {
                composition: [
                    { label: 'A', swReference: '', quantity: 1, amount: 10 },
                    { label: 'B', swReference: '', quantity: 0, amount: 2 },
                ],
            },
        };

        expect(formatter.format(refund, '$', NO_COMPOSITION)).toEqual(['A (1 x 10 $)', 'B (2 $)']);
    });
});
