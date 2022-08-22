import StoreAPIClient from "Services/shopware/StoreAPIClient";
import Shopware from "Services/shopware/Shopware"


const shopware = new Shopware();


const client = new StoreAPIClient(shopware.getStoreApiToken());


context("/mollie/ideal/issuers", () => {

    it('fetch issuers', () => {

        const request = new Promise((resolve) => {
            client.get('/mollie/ideal/issuers').then(response => {
                resolve({'data': response.data});
            });
        })

        cy.wrap(request).its('data').then(response => {
            cy.wrap(response).its('apiAlias').should('eq', 'mollie_payments_ideal_issuers')
            cy.wrap(response).its('issuers').its('length').should('be.gte', 1)
        });
    })

})

context("/mollie/ideal/store-issuer", () => {

    it('invalid customer id', () => {

        const request = new Promise((resolve) => {
            client.post('/mollie/ideal/store-issuer/cust-123/ideal_ABNANL2A').then(response => {
                resolve({'data': response.data});
            });
        })

        cy.wrap(request).its('data').then(response => {
            cy.wrap(response).its('status').should('eq', 500)
            expect(response.data.errors[0].detail).to.contain('Customer with ID cust-123 not found in Shopware');
        });
    })

})
