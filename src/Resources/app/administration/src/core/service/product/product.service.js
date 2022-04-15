export default class ProductService {

    /**
     *
     * @param product
     * @param {ProductAttributes} mollieAttributes
     */
    updateCustomFields(product, mollieAttributes) {
        // products inherit from parent products (variants).
        // as soon as something is in the custom fields, then the inheritance does not work anymore.
        // so we need to make sure to not do anything if not appropriate, or simply
        // add a clean data to our variant.
        if (!product.customFields) {
            product.customFields = {};
        }

        // we cannot simply delete the mollie_payments node in our custom fields using the API in the Shopware Admin.
        // so we make sure to at least have a valid but maybe "empty" structure in it
        product.customFields = mollieAttributes.toArray(product.customFields);
    }

}
