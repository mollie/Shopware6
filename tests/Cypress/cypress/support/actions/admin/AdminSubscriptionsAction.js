export default class AdminSubscriptionsAction {

    /**
     *
     */
    openSubscriptions() {
        cy.visit('/admin#/mollie/payments/subscriptions');
    }

    /**
     *
     * @param rowIndex
     */
    openSubscription(rowIndex) {

        const selector = '.sw-data-grid__row--' + rowIndex + ' > .sw-data-grid__cell--description > .sw-data-grid__cell-content';

        cy.get(selector, {timeout: 10000}).click();

        cy.contains('h2', 'Mollie subscription', {timeout: 10000});
    }

}
