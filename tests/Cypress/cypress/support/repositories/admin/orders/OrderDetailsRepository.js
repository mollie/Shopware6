export default class OrdersListRepository {

    /**
     *
     * @returns {Cypress.Chainable<JQuery<HTMLElement>>}
     */
    getMollieActionsButton() {
        return cy.get('[style="grid-template-columns: 1fr auto; gap: 16px; place-items: stretch;"] > [align="right"] > .sw-button-group > .sw-context-button > .sw-button');
    }

    /**
     *
     * @returns {Cypress.Chainable<JQuery<HTMLElement>>}
     */
    getMollieActionButtonShipOrder() {
        return cy.get('.sw-order-line-items-grid__actions-ship-button');
    }

    /**
     *
     * @returns {Cypress.Chainable<JQuery<HTMLElement>>}
     */
    getMollieRefundManagerButton() {
        return cy.get('.sw-order-line-items-grid__actions-refund-btn');
    }

    /**
     *
     * @returns {Cypress.Chainable<JQuery<HTMLElement>>}
     */
    getSubscriptionBadge() {
        return cy.get('.mollie-order-user-card-subscription-badge');
    }

    /**
     *
     * @returns {Cypress.Chainable<JQuery<HTMLElement>>}
     */
    getPaymentReferenceTitle() {
        return cy.get('.mollie-order-user-card-payment-reference-title');
    }

    /**
     *
     * @returns {Cypress.Chainable<JQuery<HTMLElement>>}
     */
    getPaymentReferenceValue() {
        return cy.get('.mollie-order-user-card-payment-reference-value');
    }

}
