import Shopware from "Services/shopware/Shopware";

const shopware = new Shopware();


export default class AdminPluginAction {

    /**
     *
     */
    openPluginConfiguration() {
        cy.intercept('**').as('admin');
        if (shopware.isVersionGreaterEqual('6.4')) {
            cy.visit('/admin#/sw/extension/config/MolliePayments');
        } else {
            cy.visit('/admin#/sw/plugin/settings/MolliePayments');
        }

        cy.wait('@admin');
    }

    savePlugConfiguration() {
        cy.intercept('**').as('admin');
        cy.get('.sw-extension-config__save-action').click();
        cy.wait('@admin');
    }

}
