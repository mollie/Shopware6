export default class SubscriptionRepository {

    /**
     *
     * @param index
     * @returns {Cypress.Chainable<JQuery<HTMLElement>>}
     */
    getSubscriptionContextMenuButton(index) {
        return cy.get(':nth-child(' + (index + 1) + ') > .order-wrapper > .order-item-header > .flex-wrap > .col-1 > #accountOrderDropdown');
    }

    /**
     *
     * @param index
     * @returns {Cypress.Chainable<JQuery<HTMLElement>>}
     */
    getSubscriptionViewButton(index) {
        return cy.get(':nth-child(' + (index + 1) + ') > .order-wrapper > .order-item-header > .flex-wrap > .subscription-toggle-button-wrapper > .btn');
    }
}