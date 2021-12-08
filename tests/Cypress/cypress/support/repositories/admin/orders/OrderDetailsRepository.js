export default class OrdersListRepository {

    /**
     *
     * @returns {Cypress.Chainable<JQuery<HTMLElement>>}
     */
    getMollieRefundManagerButton() {
        return cy.get('[style="grid-template-columns: 1fr auto; gap: 16px; place-items: stretch;"] > [align="right"] > .sw-button-group > .sw-button');
    }

}
