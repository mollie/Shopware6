export default class SubscriptionDetailsRepository {

    getMollieCustomerIdField() {
        return cy.get('.cy-subscription-customer-id input');
    }

    getCreatedAtField() {
        return cy.get('.cy-subscription-formatted-created-at input');
    }

    getMollieSubscriptionIdField() {
        return cy.get('.cy-subscription-mollie-id input');
    }


    getMandateField() {
        return cy.get('.cy-subscription-mandate-id input');
    }

    getCanceledAtField() {
        return cy.get('.cy-subscription-formatted-canceled-at input');
    }

    getStatusField() {
        return cy.get('.cy-subscription-translated-status input', {timeout: 20000});
    }

    getNextPaymentAtField() {
        return cy.get('.cy-subscription-formatted-next-payment-at input');
    }

    getLastRemindedAtField() {
        return cy.get('.cy-subscription-formatted-last-reminded-at input');
    }

    getPauseButton() {
        return cy.get('.cy-btn-pause');
    }

    getResumeButton() {
        return cy.get('.cy-btn-resume');
    }

    getSkipButton() {
        return cy.get('.cy-btn-skip');
    }

    getCancelButton() {
        return cy.get('.smart-bar__actions button[class*="-button"]');
    }

    getHistoryCommentSelector(rowIndex) {
        return '.sw-grid__row--' + rowIndex + ' > :nth-child(5) > .sw-grid__cell-content';
    }

    getHistoryStatusFromSelector(rowIndex) {
        return '.sw-grid__row--' + rowIndex + ' > :nth-child(3) > .sw-grid__cell-content';
    }

    getHistoryStatusToSelector(rowIndex) {
        return '.sw-grid__row--' + rowIndex + ' > :nth-child(4) > .sw-grid__cell-content';
    }

    getConfirmButton() {
        return cy.get('.sw-confirm-modal__button-confirm');

    }

}