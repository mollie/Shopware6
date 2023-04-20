import Shopware from "Services/shopware/Shopware";

const shopware = new Shopware();

export default class OrderDetailsRepository {


    /**
     *
     * @returns {Cypress.Chainable<JQuery<HTMLElement>>}
     */
    getDeliveryStatusTop() {
        if(shopware.isVersionGreaterEqual('6.5')){
            return cy.get('.sw-order-general-info__order-state-delivery .sw-block-field__block .sw-single-select__selection-text');
        }
        return cy.get(':nth-child(2) > .sw-order-state-select > .sw-field > .sw-block-field__block > #sw-field--selectedActionName');
    }

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
    getMollieActionButtonShipThroughMollie() {
        return cy.get('.sw-order-line-items-grid__actions-ship-button');
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

    /**
     *
     * @returns {Cypress.Chainable<JQuery<HTMLElement>>}
     */
    getLineItemActionsButton(nthItem) {
        return cy.get('.sw-data-grid__row--' + (nthItem - 1) + ' > .sw-data-grid__cell--actions > .sw-data-grid__cell-content > .sw-context-button > .sw-context-button__button');
    }

    /**
     *
     * @returns {Cypress.Chainable<JQuery<HTMLElement>>}
     */
    getLineItemActionsButtonShipThroughMollie() {
        return cy.contains('Ship through Mollie');
    }

    /**
     *
     * @returns {Cypress.Chainable<JQuery<HTMLElement>>}
     */
    getEditButton() {
        return cy.get('.smart-bar__actions > :nth-child(1) > .sw-button');
    }

    /**
     *
     * @param trackingCode
     */
    getTrackingCode(trackingCode) {
        if (shopware.isVersionGreaterEqual('6.5')) {
            return  cy.get('.sw-order-user-card__tracking-code-select input.sw-select-selection-list__input');
        }
        return cy.get(':nth-child(6) > .sw-field > .sw-block-field__block > .sw-select__selection > .sw-select-selection-list > li > .sw-select-selection-list__input');
    }

    /**
     *
     * @returns {Cypress.Chainable<JQuery<HTMLElement>>}
     */
    getSaveButton() {
        return cy.get('.sw-button-process');
    }

    /**
     *
     * @returns {Cypress.Chainable<JQuery<HTMLElement>>}
     */
    getOrderSummarySection() {
        return cy.get('.sw-order-detail__summary-data');
    }

    getOrderDetailsTab() {
        return cy.get('.sw-order-detail__tabs .sw-order-detail__tabs-tab-details')
    }
}
