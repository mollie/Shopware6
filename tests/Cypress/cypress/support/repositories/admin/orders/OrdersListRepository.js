import Shopware from "Services/shopware/Shopware";

const shopware = new Shopware();


export default class OrdersListRepository {


    /**
     *
     * @returns {Cypress.Chainable<JQuery<HTMLElement>>}
     */
    getLatestOrderNumber() {
        return cy.get('.sw-data-grid__row--0 > .sw-data-grid__cell--orderNumber > .sw-data-grid__cell-content > a');
    }

    /**
     *
     * @returns {string}
     */
    getLatestOrderStatusLabelSelector() {
        return '.sw-data-grid__row--0 > .sw-data-grid__cell--stateMachineState-name > .sw-data-grid__cell-content';
    }

    /**
     *
     * @returns {string}
     */
    getLatestPaymentStatusLabelSelector() {
        return '.sw-data-grid__row--0 > [class*="ansaction-stateMachineState-name"] > .sw-data-grid__cell-content';
    }

}