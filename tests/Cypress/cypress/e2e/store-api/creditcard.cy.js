import StoreAPIClient from "Services/shopware/StoreAPIClient";
import Shopware from "Services/shopware/Shopware"


const shopware = new Shopware();

const client = new StoreAPIClient(shopware.getStoreApiToken());

const storeApiPrefix = '/store-api';


context(storeApiPrefix +"/mollie/creditcard/store-token", () => {

    it('invalid customer id @core', () => {

        const request = new Promise((resolve) => {
            client.post('/mollie/creditcard/store-token/cust-123/tk_123').then(response => {
                resolve({'data': response.data});
            });
        })

        cy.wrap(request).its('data').then(response => {
            cy.wrap(response).its('status').should('eq', 500)
            expect(response.data.errors[0].detail).to.contain('Customer with ID cust-123 not found in Shopware');
        });
    })

})
