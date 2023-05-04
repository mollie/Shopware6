import Shopware from "Services/shopware/Shopware";

const shopware = new Shopware();


export default class AdminPluginAction {

    /**
     *
     */
    openPluginConfiguration() {

        if (shopware.isVersionGreaterEqual('6.4')) {
            cy.visit('/admin#/sw/extension/config/MolliePayments');
        } else {
            cy.visit('/admin#/sw/plugin/settings/MolliePayments');
        }

        cy.wait(2000);
    }

}
