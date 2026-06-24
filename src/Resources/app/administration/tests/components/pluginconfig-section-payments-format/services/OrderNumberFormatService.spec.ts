import { describe, expect, test } from 'vitest';
import OrderNumberFormatService from '../../../../src/module/mollie-payments/components/mollie-pluginconfig-section-payments-format/services/OrderNumberFormatService';

const service = new OrderNumberFormatService();

describe('OrderNumberFormatService.format', () => {
    test('replaces both placeholders', () => {
        expect(service.format('Order {ordernumber} / Customer {customernumber}', '1000', '5000')).toBe(
            'Order 1000 / Customer 5000',
        );
    });

    test('replaces every occurrence of a placeholder', () => {
        expect(service.format('{ordernumber}-{ordernumber}', '42', '7')).toBe('42-42');
    });

    test('leaves a template without placeholders unchanged', () => {
        expect(service.format('static-prefix', '1', '2')).toBe('static-prefix');
    });

    test('returns an empty string for an empty template', () => {
        expect(service.format('', '1000', '5000')).toBe('');
    });
});
