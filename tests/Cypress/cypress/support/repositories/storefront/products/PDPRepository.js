import Shopware from "Services/shopware/Shopware";

const shopware = new Shopware();

export default class PDPRepository {

    /**
     *
     * @returns {*}
     */
    getAddToCartButton() {
        if (shopware.isVersionGreaterEqual('6.5')) {
            // our apple pay button is also the same class
            // but for now we cannot change it, so we use the first button
            // which is the shopware one (if it would select the wrong one as first
            // the rest of tests wouldnt work anyway, so thats ok).
            return cy.get('.btn-buy').first();
        } else {
            return cy.get('.buy-widget-container > .col-8 > .btn');
        }
    }

    /**
     *
     * @returns {*}
     */
    getQuantityDropdown() {
        return cy.get('.col-4 > .custom-select');
    }

    /**
     *
     * @returns {*}
     */
    getQuantityBtnUp() {
        return cy.get('.btn-plus');
    }

    /**
     *
     * @returns {Cypress.Chainable<JQuery<HTMLElement>>}
     */
    getApplePayDirectButton() {
        return cy.get('.mollie-apple-pay-direct-pdp > div > .js-apple-pay');
    }

}
