import StoreAPIClient from "Services/shopware/StoreAPIClient";
import Shopware from "Services/shopware/Shopware"


const shopware = new Shopware();


const client = new StoreAPIClient(shopware.getStoreApiToken());

const storeApiPrefix = '/store-api';


context(storeApiPrefix +"/mollie/ideal/issuers", () => {

    it('C1341122: POS fetch terminals (Store API)', () => {

        const request = new Promise((resolve) => {
            client.get('/mollie/pos/terminals').then(response => {
                resolve({'data': response.data});
            });
        })

        cy.wrap(request).its('data').then(response => {
            cy.wrap(response).its('apiAlias').should('eq', 'mollie_payments_pos_terminals')
            cy.wrap(response).its('terminals').its('length').should('be.gte', 1)
        });
    })

})

context(storeApiPrefix +"/mollie/ideal/store-issuer", () => {

    it('C1341123: POS store terminal with invalid customer id (Store API) @core', () => {

        const request = new Promise((resolve) => {
            client.post('/mollie/pos/store-terminal/cust-123/ideal_ABNANL2A').then(response => {
                resolve({'data': response.data});
            });
        })

        cy.wrap(request).its('data').then(response => {

            expect(response.message).to.contain('Using deprecated route, please provide "terminalId" in request body for payment');
        });
    })

})
