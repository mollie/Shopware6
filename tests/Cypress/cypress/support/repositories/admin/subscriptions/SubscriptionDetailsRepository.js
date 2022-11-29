export default class SubscriptionDetailsRepository {

    getMollieCustomerIdField() {
        return cy.get('#sw-field--subscription-mollieCustomerId');
    }

    getCreatedAtField() {
        return cy.get('#sw-field--formattedCreateAt');
    }

    getMollieSubscriptionIdField() {
        return cy.get('#sw-field--subscription-mollieId');
    }

    getMandateField() {
        return cy.get('#sw-field--subscription-mandateId');
    }

    getCanceledAtField() {
        return cy.get('#sw-field--formattedCanceledAt');
    }

    getStatusField() {
        return cy.get('#sw-field--translatedStatus');
    }

    getNextPaymentAtField() {
        return cy.get('#sw-field--formattedNextPaymentAt');
    }

    getLastRemindedAtField() {
        return cy.get('#sw-field--formattedLastRemindedAt');
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
        return cy.get('.smart-bar__actions > .sw-button');
    }

    getHistoryCardTitle() {
        return cy.get(':nth-child(3) > .sw-card > .sw-card__header > .sw-card__titles > .sw-card__title');
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