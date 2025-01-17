import StoreAPIClient from "Services/shopware/StoreAPIClient";
import Shopware from "Services/shopware/Shopware"

const shopware = new Shopware();

const client = new StoreAPIClient(shopware.getStoreApiToken());


it('C2040032: Mollie Config can be retrieved using Store-API', () => {

    const request = new Promise((resolve) => {
        client.get('/mollie/config').then(response => {
            resolve({'data': response.data});
        });
    })

    cy.wrap(request).its('data').then(response => {
        cy.wrap(response).its('apiAlias').should('eq', 'mollie_payments_config')

        cy.wrap(response).its('profileId').should('exist');
        cy.wrap(response).its('profileId').should('not.eql', '');

        cy.wrap(response).its('testMode').should('exist');
        cy.wrap(response).its('testMode').should('not.eql', '');

        cy.wrap(response).its('locale').should('exist');
        cy.wrap(response).its('locale').should('not.eql', '');

        cy.wrap(response).its('oneClickPayments').should('exist');
        cy.wrap(response).its('oneClickPayments').should('not.eql', '');
    });
})

