import StoreAPIClient from "Services/shopware/StoreAPIClient";
import Shopware from "Services/shopware/Shopware"


const shopware = new Shopware();

const storeApiClient = new StoreAPIClient(shopware.getStoreApiToken());

const storeApiPrefix = '/store-api';


describe('Apple Pay Direct - Store API Routes', () => {

    context(storeApiPrefix + "/mollie/applepay/id", () => {

        /**
         * Please note, because this is a core based Cypress test that also runs
         * without Mollie API keys, this might not return a valid ID.
         * Instead we just verify that the route is available and that the response is existing.
         */
        it('C266669: Route available /store-api/mollie/applepay/id @core', async () => {
            const response = await storeApiClient.get('/mollie/applepay/id');

            expect(response.data.apiAlias).to.eq('mollie_payments_applepay_direct_id');
            expect(response.data.success).to.exist;
            expect(response.data.id).to.exist;
        });

    })

    context(storeApiPrefix + "/mollie/applepay/enabled", () => {

        it('C266670: Route available /store-api/mollie/applepay/enabled @core', async () => {
            const response = await storeApiClient.get('/mollie/applepay/enabled');

            expect(response.data.apiAlias).to.eq('mollie_payments_applepay_direct_enabled');
            expect(response.data.enabled).to.exist;
        });

    })

    context(storeApiPrefix + "/mollie/applepay/cart", () => {

        it('C266673: Apple Pay Direct get cart structure (Store API) @core', async () => {
            const response = await storeApiClient.get('/mollie/applepay/cart');

            expect(response.data.apiAlias).to.eq('mollie_payments_applepay_direct_cart');
            expect(response.data.cart.label.length).to.be.gt(0);
            expect(response.data.cart.total.amount).to.eq(0);
        });

    })

    context(storeApiPrefix + "/mollie/applepay/validate", () => {

        it('C266674: Apple pay Direct validate merchant domain (Store API) @core', async () => {
            const response = await storeApiClient.post('/mollie/applepay/validate');

            expect(response.status).to.eq(500);
            expect(response.data.errors[0].detail).to.contain('Please provide a validation url');
        });

    })

    context(storeApiPrefix + "/mollie/applepay/shipping-methods", () => {

        it('C266675: Apple pay Direct Shipping Methods without country code (Store API) @core', async () => {
            const response = await storeApiClient.post('/mollie/applepay/shipping-methods');

            expect(response.status).to.eq(500);
            expect(response.data.errors[0].detail).to.contain('No Country Code provided');
        });

        it('C266676: Apple pay Direct Shipping Methods with country code (Store API) @core', async () => {
            const response = await storeApiClient.post('/mollie/applepay/shipping-methods', { countryCode: 'DE' });

            expect(response.data.apiAlias).to.eq('mollie_payments_applepay_direct_shipping_methods');
            expect(response.data.shippingMethods).to.exist;
        });

    })


    context(storeApiPrefix + "/mollie/applepay/shipping-method", () => {

        it('C266677: Apple pay Direct set shipping without identifier (Store API) @core', async () => {
            const response = await storeApiClient.post('/mollie/applepay/shipping-method');

            expect(response.status).to.eq(500);
            expect(response.data.errors[0].detail).to.contain('Missing shipping method identifier');
        });

        it('C266678: Apple pay Direct set shipping with invalid identifier (Store API) @core', async () => {
            const response = await storeApiClient.post('/mollie/applepay/shipping-method', { identifier: 'abc' });

            expect(response.status).to.eq(400);
            expect(response.data.errors[0].detail).to.contain('Value is not a valid UUID: abc');
        });

    })


    context(storeApiPrefix + "/mollie/applepay/pay", () => {

        it('C266680: Apple pay Direct pay with invalid payment token (Store API) @core', async () => {
            const response = await storeApiClient.post('/mollie/applepay/pay');

            expect(response.status).to.eq(500);
            expect(response.data.errors[0].detail).to.contain('"paymentToken" not set in request body');
        });

    })

    context(storeApiPrefix + "/mollie/applepay/restore-cart", () => {

        it('C266681: Apple Pay Direct restore cart (Store API) @core', async () => {
            const response = await storeApiClient.post('/mollie/applepay/restore-cart');

            expect(response.data.apiAlias).to.eq('mollie_payments_applepay_direct_cart_restored');
        });

    })

})
