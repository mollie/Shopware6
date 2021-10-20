import ProductService from "../../../../src/core/service/product/product.service";
import ProductAttributes from "../../../../src/core/models/ProductAttributes";

const productService = new ProductService();


test('Product Service correctly updates Custom Fields', () => {

    const product = {
        customFields: {
            'other_data': '4',
            'mollie_payments': {
                'voucher_type': '3',
            }
        }
    };

    const attributes = new ProductAttributes(product);
    attributes.setVoucherType('1');

    productService.updateCustomFields(product, attributes);

    const expected = {
        'other_data': '4',
        'mollie_payments': {
            'voucher_type': '1',
        }
    };

    expect(product.customFields).toStrictEqual(expected);
});

test('Product Service clears Mollie Data if not valid', () => {
    const product = {
        customFields: {
            'mollie_payments': {
                'voucher_type': '1',
            }
        }
    };

    const attributes = new ProductAttributes(product);
    attributes.clearVoucherType();

    productService.updateCustomFields(product, attributes);

    const expected = {
        'mollie_payments': {}
    };

    expect(product.customFields).toStrictEqual(expected);
});

test('Product Service only cleans Mollie Data if not existing', () => {
    const product = {
        customFields: {
            'other_data': '4',
            'mollie_payments': {
                'voucher_type': '3',
            }
        }
    };

    const attributes = new ProductAttributes(product);
    attributes.clearVoucherType();

    productService.updateCustomFields(product, attributes);

    const expected = {
        'other_data': '4',
        'mollie_payments': {}
    };

    expect(product.customFields).toStrictEqual(expected);
});

test('Product Service does nothing if no data exists at all for Mollie', () => {

    // we create a product without mollie data
    const product = {
        customFields: {
            'other_data': '4'
        }
    };

    // we also make sure that our attributes are completely
    // invalid and not used
    const attributes = new ProductAttributes(product);
    attributes.clearVoucherType();

    productService.updateCustomFields(product, attributes);

    const expected = {
        'other_data': '4'
    };

    expect(product.customFields).toStrictEqual(expected);
});
