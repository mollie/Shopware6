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

    /**
     *
     * @param index
     * @returns {Cypress.Chainable<JQuery<HTMLElement>>}
     */
    getSubscriptionEditBillingAddressButton(index) {
        return cy.get('[data-test="btn-subscription-edit-billing-address"]').eq(index);
    }

    /**
     *
     * @param index
     * @returns {Cypress.Chainable<JQuery<HTMLElement>>}
     */
    getSubscriptionEditShippingAddressModal(index) {
        return cy.get('[data-test="btn-subscription-edit-shipping-address"]').eq(index);
    }

    /**
     *
     * @returns {Cypress.Chainable<JQuery<HTMLElement>>}
     */
    getSubscriptionEditBillingAddressModalSaveButton(index) {
        return cy.get('[data-test="btn-subscription-edit-billing-address-save"]').eq(index);
    }

    /**
     *
     * @returns {Cypress.Chainable<JQuery<HTMLElement>>}
     */
    getSubscriptionEditShippingAddressModalSaveButton(index) {
        return cy.get('[data-test="btn-subscription-edit-shipping-address-save"]').eq(index);
    }

}