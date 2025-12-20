import StoreAPIClient from "Services/shopware/StoreAPIClient";
import Shopware from "Services/shopware/Shopware"


const shopware = new Shopware();

const client = new StoreAPIClient(shopware.getStoreApiToken());

const storeApiPrefix = '/store-api';


context(storeApiPrefix + "/mollie/creditcard/store-token", () => {

    it('C266682: Store Credit Card Token with invalid customer ID (Store API) @core', () => {

        const request = new Promise((resolve) => {
            client.post('/mollie/creditcard/store-token/cust-123/tk_123').then(response => {
                resolve({'data': response.data});
            });
        })

        cy.wrap(request).its('data').then(response => {

            expect(response.message).to.contain('Using deprecated route, please provide "creditCardToken" in request body for payment');
        });
    })

})
