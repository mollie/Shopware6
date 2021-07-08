export default class AccountOrdersAction {

    /**
     *
     * @param status
     */
    assertLatestOrderBadge(status) {
        const selector = ':nth-child(1) > .order-wrapper > .order-item-header > .flex-wrap > .col-sm > .order-table-header-order-status > .badge';
        cy.contains(selector, status);
    }

    /**
     *
     * @param status
     */
    assertLatestOrderPaymentStatus(status) {
        const selector = ':nth-child(1) > .order-wrapper > .order-item-header > .order-table-header-order-table-body > :nth-child(2) > .order-table-body-value';
        cy.contains(selector, status);
    }

}
