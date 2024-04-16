import ProductService from '../../../../src/core/service/product/product.service';
import ProductAttributes from '../../../../src/core/models/ProductAttributes';

const productService = new ProductService();

test('Product Service does nothing if no data exists at all for Mollie', () => {

    // we create a product without mollie data
    const product = {
        customFields: {
            'other_data': '4',
        },
    };

    // we also make sure that our attributes are completely
    // invalid and not used
    const attributes = new ProductAttributes(product);

    productService.updateCustomFields(product, attributes);

    const expected = {
        'other_data': '4',
    };

    expect(product.customFields).toStrictEqual(expected);
});
