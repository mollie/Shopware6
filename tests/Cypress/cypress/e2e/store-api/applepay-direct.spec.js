import StoreAPIClient from "Services/shopware/StoreAPIClient";
import Shopware from "Services/shopware/Shopware"


const shopware = new Shopware();


const client = new StoreAPIClient(shopware.getStoreApiToken());


context("/mollie/applepay/id", () => {

    it('fetch id', () => {

        const request = new Promise((resolve) => {
            client.get('/mollie/applepay/id').then(response => {
                resolve({'data': response.data});
            });
        })

        cy.wrap(request).its('data').then(data => {
            cy.wrap(data).its('apiAlias').should('eq', 'mollie_payments_applepay_direct_id')
            cy.wrap(data).its('value').its('length').should('be.gte', 0)
        });
    })

})

context("/mollie/applepay/enabled", () => {

    it('not enabled', () => {

        const request = new Promise((resolve) => {
            client.get('/mollie/applepay/enabled').then(response => {
                resolve({'data': response.data});
            });
        })

        cy.wrap(request).its('data').then(data => {
            cy.wrap(data).its('apiAlias').should('eq', 'mollie_payments_applepay_direct_enabled')
            cy.wrap(data).its('enabled').should('eq', false)
        });
    })

})

context("/mollie/applepay/add-product", () => {

    it('add product with invalid quantity', () => {

        const request = new Promise((resolve) => {
            client.post('/mollie/applepay/add-product', {"productId": "unknown", quantity: 0}).then(response => {
                resolve({'data': response.data});
            });
        })

        cy.wrap(request).its('data').then(response => {
            cy.wrap(response).its('status').should('eq', 500)
            cy.wrap(response).its('data.errors').should('not.be.empty')
            expect(response.data.errors[0].detail).to.contain('Please provide a valid quantity > 0!');
        });

    })

    it('add product with invalid ID', () => {

        const request = new Promise((resolve) => {
            client.post('/mollie/applepay/add-product', {"productId": "unknown", quantity: 1}).then(response => {
                resolve({'data': response.data});
            });
        })

        cy.wrap(request).its('data').then(response => {
            cy.wrap(response).its('status').should('eq', 400)
            expect(response.data.errors[0].detail).to.contain('Value is not a valid UUID: unknown');
        });
    })

})


context("/mollie/applepay/cart", () => {

    it('get cart structure', () => {

        const request = new Promise((resolve) => {
            client.get('/mollie/applepay/cart').then(response => {
                resolve({'data': response.data});
            });
        })

        cy.wrap(request).its('data').then(response => {

            cy.wrap(response).its('apiAlias').should('eq', 'mollie_payments_applepay_direct_cart')

            cy.wrap(response).its('cart.label').should('eq', 'Storefront (molliePayments.testMode.label)')
            cy.wrap(response).its('cart.total.amount').should('eq', 0)
        });
    })

})

context("/mollie/applepay/validate", () => {

    it('no url provided', () => {

        const request = new Promise((resolve) => {
            client.post('/mollie/applepay/validate').then(response => {
                resolve({'data': response.data});
            });
        })

        cy.wrap(request).its('data').then(response => {
            cy.wrap(response).its('status').should('eq', 500)
            expect(response.data.errors[0].detail).to.contain('Please provide a validation url!');
        });
    })

})

context("/mollie/applepay/shipping-methods", () => {

    it('no country code provided', () => {

        const request = new Promise((resolve) => {
            client.post('/mollie/applepay/shipping-methods').then(response => {
                resolve({'data': response.data});
            });
        })

        cy.wrap(request).its('data').then(response => {
            cy.wrap(response).its('status').should('eq', 500)
            expect(response.data.errors[0].detail).to.contain('No Country Code provided!');
        });
    })

    it('with valid country code', () => {

        const request = new Promise((resolve) => {
            client.post('/mollie/applepay/shipping-methods', {'countryCode': 'DE'}).then(response => {
                resolve({'data': response.data});
            });
        })

        cy.wrap(request).its('data').then(response => {
            cy.wrap(response).its('apiAlias').should('eq', 'mollie_payments_applepay_direct_shipping_methods')
            cy.wrap(response).its('shippingMethods').its('length').should('be.gte', 1)
        });
    })

})


context("/mollie/applepay/shipping-method", () => {

    it('no shipping identifier provided', () => {

        const request = new Promise((resolve) => {
            client.post('/mollie/applepay/shipping-method').then(response => {
                resolve({'data': response.data});
            });
        })

        cy.wrap(request).its('data').then(response => {
            cy.wrap(response).its('status').should('eq', 500)
            expect(response.data.errors[0].detail).to.contain('Please provide a Shipping Method identifier!');
        });
    })

    it('with invalid identifier', () => {

        const request = new Promise((resolve) => {
            client.post('/mollie/applepay/shipping-method', {'identifier': 'abc'}).then(response => {
                resolve({'data': response.data});
            });
        })

        cy.wrap(request).its('data').then(response => {
            cy.wrap(response).its('status').should('eq', 400)
            expect(response.data.errors[0].detail).to.contain('Value is not a valid UUID: abc');
        });
    })

})


context("/mollie/applepay/restore-cart", () => {

    it('with invalid identifier', () => {

        const request = new Promise((resolve) => {
            client.post('/mollie/applepay/restore-cart').then(response => {
                resolve({'data': response.data});
            });
        })

        cy.wrap(request).its('data').then(response => {
            cy.wrap(response).its('apiAlias').should('eq', 'mollie_payments_applepay_direct_cart_restored')
        });
    })

})