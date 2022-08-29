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
        it('Route is available @core', () => {

            const request = new Promise((resolve) => {
                storeApiClient.get('/mollie/applepay/id').then(response => {
                    resolve({'data': response.data});
                });
            })

            cy.wrap(request).its('data').then(data => {
                cy.wrap(data).its('apiAlias').should('eq', 'mollie_payments_applepay_direct_id')
                cy.wrap(data).its('success').should('exist', true)
                cy.wrap(data).its('id').should('exist', true)
            });
        })

    })

    context(storeApiPrefix + "/mollie/applepay/enabled", () => {

        it('Route is available @core', () => {

            const request = new Promise((resolve) => {
                storeApiClient.get('/mollie/applepay/enabled').then(response => {
                    resolve({'data': response.data});
                });
            })

            cy.wrap(request).its('data').then(data => {
                cy.wrap(data).its('apiAlias').should('eq', 'mollie_payments_applepay_direct_enabled')
                cy.wrap(data).its('enabled').should('exist', true)
            });
        })

    })

    context(storeApiPrefix + "/mollie/applepay/add-product", () => {

        it('add product with invalid quantity @core', () => {

            const request = new Promise((resolve) => {
                storeApiClient.post('/mollie/applepay/add-product', {"productId": "unknown", quantity: 0}).then(response => {
                    resolve({'data': response.data});
                });
            })

            cy.wrap(request).its('data').then(response => {
                cy.wrap(response).its('status').should('eq', 500)
                cy.wrap(response).its('data.errors').should('not.be.empty')
                expect(response.data.errors[0].detail).to.contain('Please provide a valid quantity > 0!');
            });

        })

        it('add product with invalid ID @core', () => {

            const request = new Promise((resolve) => {
                storeApiClient.post('/mollie/applepay/add-product', {"productId": "unknown", quantity: 1}).then(response => {
                    resolve({'data': response.data});
                });
            })

            cy.wrap(request).its('data').then(response => {
                cy.wrap(response).its('status').should('eq', 400)
                expect(response.data.errors[0].detail).to.contain('Value is not a valid UUID: unknown');
            });
        })

    })


    context(storeApiPrefix + "/mollie/applepay/cart", () => {

        it('get cart structure @core', () => {

            const request = new Promise((resolve) => {
                storeApiClient.get('/mollie/applepay/cart').then(response => {
                    resolve({'data': response.data});
                });
            })

            cy.wrap(request).its('data').then(response => {
                cy.wrap(response).its('apiAlias').should('eq', 'mollie_payments_applepay_direct_cart')
                cy.wrap(response).its('cart.label').its('length').should('be.gt', 0)
                cy.wrap(response).its('cart.total.amount').should('eq', 0)
            });
        })

    })

    context(storeApiPrefix + "/mollie/applepay/validate", () => {

        it('no url provided @core', () => {

            const request = new Promise((resolve) => {
                storeApiClient.post('/mollie/applepay/validate').then(response => {
                    resolve({'data': response.data});
                });
            })

            cy.wrap(request).its('data').then(response => {
                cy.wrap(response).its('status').should('eq', 500)
                expect(response.data.errors[0].detail).to.contain('Please provide a validation url!');
            });
        })

    })

    context(storeApiPrefix + "/mollie/applepay/shipping-methods", () => {

        it('no country code provided @core', () => {

            const request = new Promise((resolve) => {
                storeApiClient.post('/mollie/applepay/shipping-methods').then(response => {
                    resolve({'data': response.data});
                });
            })

            cy.wrap(request).its('data').then(response => {
                cy.wrap(response).its('status').should('eq', 500)
                expect(response.data.errors[0].detail).to.contain('No Country Code provided!');
            });
        })

        it('with valid country code @core', () => {

            const request = new Promise((resolve) => {
                storeApiClient.post('/mollie/applepay/shipping-methods', {'countryCode': 'DE'}).then(response => {
                    resolve({'data': response.data});
                });
            })

            cy.wrap(request).its('data').then(response => {
                cy.wrap(response).its('apiAlias').should('eq', 'mollie_payments_applepay_direct_shipping_methods')
                cy.wrap(response).its('shippingMethods').should('exist', true);
            });
        })

    })


    context(storeApiPrefix + "/mollie/applepay/shipping-method", () => {

        it('no shipping identifier provided @core', () => {

            const request = new Promise((resolve) => {
                storeApiClient.post('/mollie/applepay/shipping-method').then(response => {
                    resolve({'data': response.data});
                });
            })

            cy.wrap(request).its('data').then(response => {
                cy.wrap(response).its('status').should('eq', 500)
                expect(response.data.errors[0].detail).to.contain('Please provide a Shipping Method identifier!');
            });
        })

        it('with invalid identifier @core', () => {

            const request = new Promise((resolve) => {
                storeApiClient.post('/mollie/applepay/shipping-method', {'identifier': 'abc'}).then(response => {
                    resolve({'data': response.data});
                });
            })

            cy.wrap(request).its('data').then(response => {
                cy.wrap(response).its('status').should('eq', 400)
                expect(response.data.errors[0].detail).to.contain('Value is not a valid UUID: abc');
            });
        })

    })


    context(storeApiPrefix + "/mollie/applepay/pay", () => {

        it('with invalid payment token @core', () => {

            const request = new Promise((resolve) => {
                storeApiClient.post('/mollie/applepay/pay').then(response => {
                    resolve({'data': response.data});
                });
            })

            cy.wrap(request).its('data').then(response => {
                cy.wrap(response).its('status').should('eq', 500)
                expect(response.data.errors[0].detail).to.contain('PaymentToken not found!')
            });
        })

    })

    context(storeApiPrefix + "/mollie/applepay/restore-cart", () => {

        it('with invalid identifier @core', () => {

            const request = new Promise((resolve) => {
                storeApiClient.post('/mollie/applepay/restore-cart').then(response => {
                    resolve({'data': response.data});
                });
            })

            cy.wrap(request).its('data').then(response => {
                cy.wrap(response).its('apiAlias').should('eq', 'mollie_payments_applepay_direct_cart_restored')
            });
        })

    })

})