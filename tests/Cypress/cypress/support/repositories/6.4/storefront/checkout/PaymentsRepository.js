export default class PaymentsRepository {

    /**
     *
     * @returns {*}
     */
    getSubmitButton() {
        return cy.get('#confirmPaymentForm > .btn-primary');
    }

}
